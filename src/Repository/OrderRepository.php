<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByUser(User $user, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC');
        
        if ($limit) {
            $qb->setMaxResults($limit);
        }
        
        return $qb->getQuery()->getResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findReadyToShip(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', Order::STATUS_READY_TO_SHIP)
            ->orderBy('o.createdAt', 'ASC')
            ->addOrderBy('o.id', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findPendingFulfillment(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', [
                Order::STATUS_PAID,
                Order::STATUS_PROCESSING,
            ])
            ->orderBy('o.paidAt', 'ASC')
            ->addOrderBy('o.createdAt', 'ASC')
            ->addOrderBy('o.id', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findRecentOrders(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array<int, string> $statuses
     */
    public function countByStatuses(array $statuses): int
    {
        if ($statuses === []) {
            return 0;
        }

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countShippedToday(?\DateTimeInterface $referenceDay = null): int
    {
        $day = $referenceDay
            ? \DateTimeImmutable::createFromInterface($referenceDay)->setTime(0, 0)
            : new \DateTimeImmutable('today');

        $nextDay = $day->modify('+1 day');

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status = :status')
            ->andWhere('o.shippedAt >= :dayStart')
            ->andWhere('o.shippedAt < :dayEnd')
            ->setParameter('status', Order::STATUS_SHIPPED)
            ->setParameter('dayStart', $day)
            ->setParameter('dayEnd', $nextDay)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalRevenue(\DateTimeInterface $from = null, \DateTimeInterface $to = null): string
    {
        $qb = $this->createQueryBuilder('o')
            ->select('SUM(o.total)')
            ->andWhere('o.status NOT IN (:excludedStatuses)')
            ->setParameter('excludedStatuses', [
                Order::STATUS_CANCELLED,
                Order::STATUS_REFUNDED,
            ]);
        
        if ($from) {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', $from);
        }
        
        if ($to) {
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', $to);
        }
        
        return $qb->getQuery()->getSingleScalarResult() ?? '0.00';
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.orderNumber = :orderNumber')
            ->setParameter('orderNumber', $orderNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getOrdersByRegion(\DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->select('a.region, COUNT(o.id) as orderCount, SUM(o.total) as totalRevenue')
            ->leftJoin('o.shippingAddress', 'a')
            ->andWhere('o.status NOT IN (:excludedStatuses)')
            ->setParameter('excludedStatuses', [
                Order::STATUS_CANCELLED,
                Order::STATUS_REFUNDED,
            ])
            ->groupBy('a.region');
        
        if ($from) {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', $from);
        }
        
        if ($to) {
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', $to);
        }
        
        return $qb->getQuery()->getResult();
    }
}
