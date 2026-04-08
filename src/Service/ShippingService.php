<?php

namespace App\Service;

use App\Entity\Address;

class ShippingService
{
    // Shipping rates by region (in PHP)
    private const RATES = [
        Address::REGION_METRO_MANILA => '100.00',
        Address::REGION_LUZON => '150.00',
        Address::REGION_VISAYAS => '200.00',
        Address::REGION_MINDANAO => '250.00',
    ];

    // Free shipping threshold
    private const FREE_SHIPPING_THRESHOLD = '2500.00';

    // Estimated delivery days by region
    private const DELIVERY_DAYS = [
        Address::REGION_METRO_MANILA => [1, 2],
        Address::REGION_LUZON => [2, 4],
        Address::REGION_VISAYAS => [3, 5],
        Address::REGION_MINDANAO => [4, 7],
    ];

    public function calculateShippingCost(Address $address, string $orderTotal, bool $hasFreeShippingPromo = false): string
    {
        // Free shipping promo overrides everything
        if ($hasFreeShippingPromo) {
            return '0.00';
        }

        // Free shipping for orders above threshold
        if (bccomp($orderTotal, self::FREE_SHIPPING_THRESHOLD, 2) >= 0) {
            return '0.00';
        }

        $region = $address->getRegion();
        return self::RATES[$region] ?? self::RATES[Address::REGION_LUZON];
    }

    public function getShippingRates(): array
    {
        $rates = [];
        foreach (self::RATES as $region => $rate) {
            $rates[] = [
                'region' => $region,
                'region_label' => Address::REGIONS[$region] ?? $region,
                'rate' => $rate,
                'delivery_days' => self::DELIVERY_DAYS[$region] ?? [3, 7],
            ];
        }
        return $rates;
    }

    public function getFreeShippingThreshold(): string
    {
        return self::FREE_SHIPPING_THRESHOLD;
    }

    public function getEstimatedDeliveryDate(Address $address): array
    {
        $region = $address->getRegion();
        $days = self::DELIVERY_DAYS[$region] ?? [3, 7];

        $minDate = (new \DateTime())->modify('+' . $days[0] . ' days');
        $maxDate = (new \DateTime())->modify('+' . $days[1] . ' days');

        return [
            'min_days' => $days[0],
            'max_days' => $days[1],
            'min_date' => $minDate,
            'max_date' => $maxDate,
            'formatted' => sprintf(
                '%s - %s',
                $minDate->format('M j'),
                $maxDate->format('M j, Y')
            ),
        ];
    }

    public function getAvailableCouriers(Address $address): array
    {
        // Return available couriers based on region
        $couriers = [
            [
                'code' => 'lbc',
                'name' => 'LBC Express',
                'logo' => 'lbc.png',
            ],
            [
                'code' => 'jt',
                'name' => 'J&T Express',
                'logo' => 'jt.png',
            ],
            [
                'code' => 'ninja_van',
                'name' => 'Ninja Van',
                'logo' => 'ninjavan.png',
            ],
        ];

        // Grab Express and Lalamove only for Metro Manila
        if ($address->getRegion() === Address::REGION_METRO_MANILA) {
            $couriers[] = [
                'code' => 'grab_express',
                'name' => 'Grab Express',
                'logo' => 'grab.png',
            ];
            $couriers[] = [
                'code' => 'lalamove',
                'name' => 'Lalamove',
                'logo' => 'lalamove.png',
            ];
        }

        return $couriers;
    }

    public function isRegionRestricted(Address $address): bool
    {
        // For vape products, certain areas may have restrictions
        // This can be expanded based on local regulations
        return false;
    }

    public function getRestrictedAreas(): array
    {
        // Return list of areas where vape shipping is restricted
        return [];
    }
}
