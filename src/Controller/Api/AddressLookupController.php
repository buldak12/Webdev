<?php

namespace App\Controller\Api;

use App\Entity\Address;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/address', name: 'api_address_')]
#[IsGranted('ROLE_USER')]
class AddressLookupController extends AbstractController
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    #[Route('/suggest', name: 'suggest', methods: ['GET'])]
    public function suggest(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if (strlen($query) < 3) {
            return $this->json([
                'success' => true,
                'data' => [],
            ]);
        }

        try {
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'query' => [
                    'q' => $query . ', Philippines',
                    'countrycodes' => 'ph',
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 6,
                ],
                'headers' => [
                    'User-Agent' => 'VapeShopPH/1.0 (+https://localhost)',
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ],
                'timeout' => 8,
            ]);

            $results = $response->toArray(false);
        } catch (\Throwable) {
            return $this->json([
                'success' => false,
                'data' => [],
                'error' => 'Address lookup is temporarily unavailable.',
            ], 503);
        }

        if (!is_array($results)) {
            return $this->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $suggestions = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $mapped = $this->mapSuggestion($result);
            if ($mapped !== null) {
                $suggestions[] = $mapped;
            }
        }

        return $this->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    #[Route('/reverse', name: 'reverse', methods: ['GET'])]
    public function reverse(Request $request): JsonResponse
    {
        $lat = (string) $request->query->get('lat', '');
        $lon = (string) $request->query->get('lon', '');

        if (!is_numeric($lat) || !is_numeric($lon)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid coordinates.',
            ], 400);
        }

        $latitude = (float) $lat;
        $longitude = (float) $lon;
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return $this->json([
                'success' => false,
                'error' => 'Coordinates out of range.',
            ], 400);
        }

        try {
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
                'query' => [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'zoom' => 18,
                ],
                'headers' => [
                    'User-Agent' => 'VapeShopPH/1.0 (+https://localhost)',
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ],
                'timeout' => 8,
            ]);

            $result = $response->toArray(false);
        } catch (\Throwable) {
            return $this->json([
                'success' => false,
                'error' => 'Reverse address lookup is temporarily unavailable.',
            ], 503);
        }

        if (!is_array($result)) {
            return $this->json([
                'success' => true,
                'data' => null,
            ]);
        }

        $mapped = $this->mapSuggestion($result);

        return $this->json([
            'success' => true,
            'data' => $mapped,
        ]);
    }

    private function mapSuggestion(array $result): ?array
    {
        $address = is_array($result['address'] ?? null) ? $result['address'] : [];

        $city = self::firstNonEmpty([
            $address['city'] ?? null,
            $address['town'] ?? null,
            $address['municipality'] ?? null,
            $address['county'] ?? null,
        ]);

        $province = self::firstNonEmpty([
            $address['state'] ?? null,
            $address['province'] ?? null,
            $address['region'] ?? null,
        ]);

        $streetAddress = self::firstNonEmpty([
            trim((string) (($address['house_number'] ?? '') . ' ' . ($address['road'] ?? ''))),
            $address['road'] ?? null,
            $address['pedestrian'] ?? null,
            $address['hamlet'] ?? null,
            $address['village'] ?? null,
        ]) ?? '';

        $barangay = self::firstNonEmpty([
            $address['suburb'] ?? null,
            $address['neighbourhood'] ?? null,
            $address['quarter'] ?? null,
            $address['city_district'] ?? null,
            $address['village'] ?? null,
        ]);

        if ($city === null && $province === null && $streetAddress === '') {
            return null;
        }

        $resolvedCity = $city ?? $province ?? '';
        $resolvedProvince = $province ?? $city ?? '';

        return [
            'id' => (string) ($result['place_id'] ?? md5((string) ($result['display_name'] ?? ''))),
            'displayName' => (string) ($result['display_name'] ?? trim($resolvedCity . ', ' . $resolvedProvince)),
            'streetAddress' => $streetAddress,
            'barangay' => $barangay,
            'city' => $resolvedCity,
            'province' => $resolvedProvince,
            'postalCode' => self::normalizePostalCode(self::firstNonEmpty([$address['postcode'] ?? null])),
            'region' => self::detectRegion(trim($resolvedProvince . ' ' . $resolvedCity)),
            'lat' => (string) ($result['lat'] ?? ''),
            'lon' => (string) ($result['lon'] ?? ''),
        ];
    }

    private static function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private static function normalizePostalCode(?string $postalCode): ?string
    {
        if ($postalCode === null) {
            return null;
        }

        if (preg_match('/\d{4}/', $postalCode, $matches) !== 1) {
            return null;
        }

        return $matches[0];
    }

    private static function detectRegion(string $provinceOrCity): string
    {
        $value = strtolower($provinceOrCity);

        if (str_contains($value, 'metro manila') || str_contains($value, 'national capital region')) {
            return Address::REGION_METRO_MANILA;
        }

        $metroManilaCities = [
            'manila',
            'quezon city',
            'makati',
            'pasig',
            'taguig',
            'paranaque',
            'caloocan',
            'mandaluyong',
            'marikina',
            'muntinlupa',
            'navotas',
            'malabon',
            'san juan',
            'las pinas',
            'valenzuela',
            'pasay',
            'pateros',
        ];

        foreach ($metroManilaCities as $city) {
            if (str_contains($value, $city)) {
                return Address::REGION_METRO_MANILA;
            }
        }

        $visayasMarkers = [
            'cebu',
            'bohol',
            'iloilo',
            'leyte',
            'samar',
            'negros',
            'aklan',
            'antique',
            'capiz',
            'guimaras',
            'siquijor',
            'biliran',
        ];

        foreach ($visayasMarkers as $marker) {
            if (str_contains($value, $marker)) {
                return Address::REGION_VISAYAS;
            }
        }

        $mindanaoMarkers = [
            'davao',
            'zamboanga',
            'bukidnon',
            'camiguin',
            'lanao',
            'misamis',
            'cotabato',
            'sarangani',
            'sultan kudarat',
            'agusan',
            'dinagat',
            'surigao',
            'basilan',
            'sulu',
            'tawi-tawi',
            'maguindanao',
        ];

        foreach ($mindanaoMarkers as $marker) {
            if (str_contains($value, $marker)) {
                return Address::REGION_MINDANAO;
            }
        }

        return Address::REGION_LUZON;
    }
}
