<?php

namespace App\Repository;

use App\Entity\Shipment;
use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shipment>
 */
class ShipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shipment::class);
    }

    public function findByOrder(Order $order): ?Shipment
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.order = :order')
            ->setParameter('order', $order)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByTrackingNumber(string $trackingNumber): ?Shipment
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.trackingNumber = :trackingNumber')
            ->setParameter('trackingNumber', $trackingNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', $status)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingPickup(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', Shipment::STATUS_PACKED)
            ->orderBy('s.packedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findInTransit(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('statuses', [
                Shipment::STATUS_PICKED_UP,
                Shipment::STATUS_IN_TRANSIT,
                Shipment::STATUS_OUT_FOR_DELIVERY,
            ])
            ->orderBy('s.shippedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findNeedingAttention(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('statuses', [
                Shipment::STATUS_FAILED,
                Shipment::STATUS_RETURNED,
            ])
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
