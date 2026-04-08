<?php

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function findByUser(User $user): ?Cart
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findBySessionId(string $sessionId): ?Cart
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOrCreateForUser(User $user): Cart
    {
        $cart = $this->findByUser($user);
        
        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->getEntityManager()->persist($cart);
            $this->getEntityManager()->flush();
        }
        
        return $cart;
    }

    public function findExpiredCarts(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.expiresAt < :now')
            ->andWhere('c.user IS NULL')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function cleanupExpiredCarts(): int
    {
        $expired = $this->findExpiredCarts();
        $count = count($expired);
        
        foreach ($expired as $cart) {
            $this->getEntityManager()->remove($cart);
        }
        
        $this->getEntityManager()->flush();
        return $count;
    }
}
