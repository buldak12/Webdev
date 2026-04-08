<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findByOrder(Order $order): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.order = :order')
            ->setParameter('order', $order)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findSuccessfulByOrder(Order $order): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.order = :order')
            ->andWhere('p.status = :status')
            ->setParameter('order', $order)
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByTransactionId(string $transactionId): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.transactionId = :transactionId')
            ->setParameter('transactionId', $transactionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPendingPayments(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('statuses', [
                Payment::STATUS_PENDING,
                Payment::STATUS_PROCESSING,
            ])
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getRevenueByGateway(\DateTimeInterface $from = null, \DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.gateway, SUM(p.amount) as totalAmount, COUNT(p.id) as transactionCount')
            ->andWhere('p.status = :status')
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->groupBy('p.gateway');
        
        if ($from) {
            $qb->andWhere('p.completedAt >= :from')->setParameter('from', $from);
        }
        
        if ($to) {
            $qb->andWhere('p.completedAt <= :to')->setParameter('to', $to);
        }
        
        return $qb->getQuery()->getResult();
    }
}
