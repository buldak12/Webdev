<?php

namespace App\Repository;

use App\Entity\ProductVariant;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductVariant>
 */
class ProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariant::class);
    }

    public function findBySku(string $sku): ?ProductVariant
    {
        return $this->createQueryBuilder('pv')
            ->andWhere('pv.sku = :sku')
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('pv')
            ->andWhere('pv.product = :product')
            ->andWhere('pv.isActive = :active')
            ->setParameter('product', $product)
            ->setParameter('active', true)
            ->orderBy('pv.flavor', 'ASC')
            ->addOrderBy('pv.nicotineStrength', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLowStock(?int $threshold = null): array
    {
        $qb = $this->createQueryBuilder('pv')
            ->join('pv.product', 'p')
            ->andWhere('pv.isActive = :active')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true);
        
        if ($threshold !== null) {
            $qb->andWhere('(pv.stock - pv.reservedStock) <= :threshold')
               ->setParameter('threshold', $threshold);
        } else {
            $qb->andWhere('(pv.stock - pv.reservedStock) <= pv.lowStockThreshold');
        }
        
        return $qb->orderBy('pv.stock', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOutOfStock(): array
    {
        return $this->createQueryBuilder('pv')
            ->join('pv.product', 'p')
            ->andWhere('pv.isActive = :active')
            ->andWhere('p.isActive = :active')
            ->andWhere('(pv.stock - pv.reservedStock) <= 0')
            ->setParameter('active', true)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getStockOverview(): array
    {
        $row = $this->createQueryBuilder('pv')
            ->select(
                'COUNT(pv.id) AS variant_count',
                'COUNT(DISTINCT p.id) AS product_count',
                'COALESCE(SUM(pv.stock), 0) AS total_stock',
                'COALESCE(SUM(pv.reservedStock), 0) AS reserved_stock',
                'COALESCE(SUM(pv.stock - pv.reservedStock), 0) AS available_stock'
            )
            ->join('pv.product', 'p')
            ->andWhere('pv.isActive = :active')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleResult();

        return [
            'variant_count' => (int) ($row['variant_count'] ?? 0),
            'product_count' => (int) ($row['product_count'] ?? 0),
            'total_stock' => (int) ($row['total_stock'] ?? 0),
            'reserved_stock' => (int) ($row['reserved_stock'] ?? 0),
            'available_stock' => (int) ($row['available_stock'] ?? 0),
        ];
    }
}
