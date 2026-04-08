<?php

namespace App\Controller\Admin;

use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class InventoryController extends AbstractController
{
    #[Route('/inventory', name: 'admin_inventory')]
    public function index(
        ProductVariantRepository $variantRepository,
        ProductRepository $productRepository,
        InventoryService $inventoryService,
        Request $request
    ): Response {
        $filter = $request->query->get('filter');
        
        if ($filter === 'low') {
            $variants = $variantRepository->findLowStock();
        } elseif ($filter === 'out') {
            $variants = $variantRepository->findOutOfStock();
        } else {
            $variants = $variantRepository->findBy(['isActive' => true], ['stock' => 'ASC']);
        }

        $summary = $inventoryService->getStockSummary();
        $products = $productRepository->findActive();

        return $this->render('admin/inventory/index.html.twig', [
            'variants' => $variants,
            'products' => $products,
            'summary' => $summary,
            'current_filter' => $filter,
        ]);
    }

    #[Route('/inventory/{id}/update', name: 'admin_inventory_update', methods: ['POST'])]
    public function updateStock(
        int $id,
        Request $request,
        ProductVariantRepository $variantRepository,
        InventoryService $inventoryService
    ): Response {
        $variant = $variantRepository->find($id);
        if (!$variant) {
            throw $this->createNotFoundException('Variant not found');
        }

        $action = $request->request->get('action');
        $quantity = (int) $request->request->get('quantity', 0);

        if ($action === 'add') {
            $inventoryService->addStock($variant, $quantity);
            $this->addFlash('success', "Added $quantity units to stock");
        } elseif ($action === 'set') {
            $variant->setStock($quantity);
            $this->addFlash('success', "Stock set to $quantity units");
        } elseif ($action === 'deduct') {
            if ($inventoryService->deductStock($variant, $quantity)) {
                $this->addFlash('success', "Deducted $quantity units from stock");
            } else {
                $this->addFlash('error', 'Not enough stock to deduct');
            }
        }

        return $this->redirectToRoute('admin_inventory');
    }

    #[Route('/inventory/{id}/threshold', name: 'admin_inventory_threshold', methods: ['POST'])]
    public function updateThreshold(
        int $id,
        Request $request,
        ProductVariantRepository $variantRepository,
        InventoryService $inventoryService
    ): Response {
        $variant = $variantRepository->find($id);
        if (!$variant) {
            throw $this->createNotFoundException('Variant not found');
        }

        $threshold = (int) $request->request->get('threshold', 10);
        $inventoryService->setLowStockThreshold($variant, $threshold);

        $this->addFlash('success', 'Low stock threshold updated');
        return $this->redirectToRoute('admin_inventory');
    }

    #[Route('/inventory/bulk-update', name: 'admin_inventory_bulk', methods: ['POST'])]
    public function bulkUpdate(
        Request $request,
        ProductVariantRepository $variantRepository,
        EntityManagerInterface $em
    ): Response {
        $updates = $request->request->all('stock') ?? [];
        
        foreach ($updates as $variantId => $newStock) {
            $variant = $variantRepository->find($variantId);
            if ($variant) {
                $variant->setStock((int) $newStock);
            }
        }

        $em->flush();
        $this->addFlash('success', 'Stock levels updated');

        return $this->redirectToRoute('admin_inventory');
    }
}
