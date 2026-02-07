<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findOneForUserWithItems(int $reservationId, int $userId): ?\App\Entity\Reservation
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.items', 'i')->addSelect('i')
            ->leftJoin('i.product', 'p')->addSelect('p')
            ->andWhere('r.id = :rid')
            ->andWhere('r.user = :uid')
            ->setParameter('rid', $reservationId)
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

}
