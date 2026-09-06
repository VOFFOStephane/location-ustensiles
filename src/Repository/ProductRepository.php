<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

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



    public function createCatalogQuery(?string $q, ?int $categoryId): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->andWhere('p.isDisabled = false')
            ->andWhere('c.isDisabled = false');

        if ($q) {
            $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.description) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($q)).'%');
        }

        if ($categoryId) {
            $qb->andWhere('c.id = :cid')
                ->setParameter('cid', $categoryId);
        }

        return $qb->orderBy('p.createdAt', 'DESC');
    }
    public function searchPaginated(string $q, int $categoryId, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->andWhere('p.isDisabled = false');

        if ($q !== '') {
            $qb->andWhere('p.name LIKE :q OR p.description LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }

        if ($categoryId > 0) {
            $qb->andWhere('c.id = :cid')
                ->setParameter('cid', $categoryId);
        }

        // total
        $qbCount = clone $qb;
        $total = (int) $qbCount
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // page
        $products = $qb
            ->orderBy('p.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [$products, $total];
    }

}
