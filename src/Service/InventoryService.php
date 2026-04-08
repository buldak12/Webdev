<?php

namespace App\Service;

use App\Entity\ProductVariant;
use App\Repository\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;

class InventoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductVariantRepository $variantRepository
    ) {}

    public function checkAvailability(ProductVariant $variant, int $quantity): bool
    {
        return $variant->getAvailableStock() >= $quantity;
    }

    public function reserveStock(ProductVariant $variant, int $quantity): bool
    {
        if (!$this->checkAvailability($variant, $quantity)) {
            return false;
        }

        $variant->reserveStock($quantity);
        $this->entityManager->flush();
        return true;
    }

    public function releaseReservedStock(ProductVariant $variant, int $quantity): void
    {
        $variant->releaseReservedStock($quantity);
        $this->entityManager->flush();
    }

    public function confirmStockDeduction(ProductVariant $variant, int $quantity): void
    {
        $variant->confirmReservedStock($quantity);
        $this->entityManager->flush();
    }

    public function deductStock(ProductVariant $variant, int $quantity): bool
    {
        if ($variant->getStock() < $quantity) {
            return false;
        }

        $variant->removeStock($quantity);
        $this->entityManager->flush();
        return true;
    }

    public function addStock(ProductVariant $variant, int $quantity, string $reason = null): void
    {
        $variant->addStock($quantity);
        $this->entityManager->flush();
    }

    public function getLowStockVariants(int $threshold = null): array
    {
        return $this->variantRepository->findLowStock($threshold);
    }

    public function getOutOfStockVariants(): array
    {
        return $this->variantRepository->findOutOfStock();
    }

    public function setLowStockThreshold(ProductVariant $variant, int $threshold): void
    {
        $variant->setLowStockThreshold($threshold);
        $this->entityManager->flush();
    }

    public function getStockSummary(): array
    {
        $lowStock = $this->variantRepository->findLowStock();
        $outOfStock = $this->variantRepository->findOutOfStock();

        return [
            'low_stock_count' => count($lowStock),
            'out_of_stock_count' => count($outOfStock),
            'low_stock_items' => $lowStock,
            'out_of_stock_items' => $outOfStock,
        ];
    }
}
