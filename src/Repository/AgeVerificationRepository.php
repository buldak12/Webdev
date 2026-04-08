<?php

namespace App\Repository;

use App\Entity\AgeVerification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgeVerification>
 */
class AgeVerificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgeVerification::class);
    }

    public function findPending(): array
    {
        return $this->createQueryBuilder('av')
            ->andWhere('av.status = :status')
            ->setParameter('status', AgeVerification::STATUS_PENDING)
            ->orderBy('av.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('av')
            ->select('COUNT(av.id)')
            ->andWhere('av.status = :status')
            ->setParameter('status', AgeVerification::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecentByStatus(string $status, int $limit = 20): array
    {
        return $this->createQueryBuilder('av')
            ->andWhere('av.status = :status')
            ->setParameter('status', $status)
            ->orderBy('av.reviewedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
