<?php

namespace App\Controller\Api;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\AddressRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/test')]
class SimpleOrderController extends AbstractController
{
    #[Route('/ping', name: 'api_test_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return $this->json(['message' => 'pong', 'timestamp' => time()]);
    }

    #[Route('/order', name: 'api_test_order', methods: ['POST'])]
    public function createSimpleOrder(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        ProductVariantRepository $variantRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        // Return structure that matches mobile app expectations
        return $this->json([
            'message' => 'Order created successfully',
            'order' => [
                'id' => 999,
                'order_number' => 'TEST-' . time(),
                'status' => 'awaiting_payment',
                'total' => '1000.00',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ], Response::HTTP_CREATED);
    }
}
