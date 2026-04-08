<?php

namespace App\Repository;

use App\Entity\LoyaltyTransaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoyaltyTransaction>
 */
class LoyaltyTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoyaltyTransaction::class);
    }

    public function findByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('lt')
            ->andWhere('lt.user = :user')
            ->setParameter('user', $user)
            ->orderBy('lt.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTotalEarnedByUser(User $user): int
    {
        $result = $this->createQueryBuilder('lt')
            ->select('SUM(lt.points)')
            ->andWhere('lt.user = :user')
            ->andWhere('lt.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', LoyaltyTransaction::TYPE_EARNED)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (int) $result;
    }

    public function getTotalRedeemedByUser(User $user): int
    {
        $result = $this->createQueryBuilder('lt')
            ->select('SUM(lt.points)')
            ->andWhere('lt.user = :user')
            ->andWhere('lt.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', LoyaltyTransaction::TYPE_REDEEMED)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (int) $result;
    }
}
