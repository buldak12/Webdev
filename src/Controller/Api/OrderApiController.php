<?php

namespace App\Controller\Api;

use App\Repository\ProductVariantRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class OrderApiController extends AbstractController
{
    // Admin/Staff only endpoints
    
    #[Route('/customers', name: 'api_customers_list', methods: ['GET'])]
    public function getCustomers(UserRepository $userRepository): JsonResponse
    {
        $customers = $userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_CUSTOMER%')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(fn($customer) => [
            'id' => $customer->getId(),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
        ], $customers);

        return $this->json($data);
    }

    #[Route('/customers/{id}/addresses', name: 'api_customer_addresses', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getCustomerAddresses(int $id, UserRepository $userRepository): JsonResponse
    {
        $customer = $userRepository->find($id);
        
        if (!$customer) {
            return $this->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        $data = array_map(fn($address) => [
            'id' => $address->getId(),
            'fullName' => $address->getFullName(),
            'streetAddress' => $address->getStreetAddress(),
            'barangay' => $address->getBarangay(),
            'city' => $address->getCity(),
            'province' => $address->getProvince(),
            'postalCode' => $address->getPostalCode(),
            'country' => $address->getCountry(),
            'phone' => $address->getPhone(),
        ], $customer->getAddresses()->toArray());

        return $this->json($data);
    }

    #[Route('/variants', name: 'api_variants_list', methods: ['GET'])]
    public function getVariants(ProductVariantRepository $variantRepository): JsonResponse
    {
        $variants = $variantRepository->createQueryBuilder('v')
            ->leftJoin('v.product', 'p')
            ->where('p.isActive = true')
            ->where('v.isActive = true')
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('v.flavor', 'ASC')
            ->getQuery()
            ->getResult();

        $data = array_map(fn($variant) => [
            'id' => $variant->getId(),
            'productName' => $variant->getProduct()?->getName() ?? 'Unknown',
            'productId' => $variant->getProduct()?->getId(),
            'attributes' => $variant->getVariantAttributes(),
            'flavor' => $variant->getFlavor(),
            'nicotine' => $variant->getNicotineStrength(),
            'sku' => $variant->getSku(),
            'price' => $variant->getPrice(),
            'stock' => $variant->getStock(),
        ], $variants);

        return $this->json($data);
    }
}
