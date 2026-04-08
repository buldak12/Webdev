<?php

namespace App\Repository;

use App\Entity\PromoCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromoCode>
 */
class PromoCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCode::class);
    }

    public function findByCode(string $code): ?PromoCode
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.code = :code')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActive(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('(p.startsAt IS NULL OR p.startsAt <= :now)')
            ->andWhere('(p.expiresAt IS NULL OR p.expiresAt >= :now)')
            ->andWhere('(p.usageLimit IS NULL OR p.usageCount < p.usageLimit)')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findExpired(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('p')
            ->andWhere('p.expiresAt < :now')
            ->setParameter('now', $now)
            ->orderBy('p.expiresAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
