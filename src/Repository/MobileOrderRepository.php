<?php

namespace App\Repository;

use App\Entity\MobileOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MobileOrder>
 */
class MobileOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MobileOrder::class);
    }

    public function findByEmail(string $email): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.customerEmail = :email')
            ->setParameter('email', $email)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
