<?php

namespace App\Controller\Api;

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
    public function testOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        return $this->json([
            'message' => 'Test order received',
            'received_data' => $data,
            'timestamp' => time()
        ], Response::HTTP_OK);
    }
}
