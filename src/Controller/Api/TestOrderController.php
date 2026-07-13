<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Test endpoint to verify deployment
 */
class TestOrderController extends AbstractController
{
    #[Route('/api/test-order', name: 'api_test_order', methods: ['POST', 'GET'])]
    public function testOrder(Request $request): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => 'Test endpoint is working',
            'method' => $request->getMethod(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
