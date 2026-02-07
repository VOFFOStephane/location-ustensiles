<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Reservation;
use App\Entity\ReservationItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReservationItem>
 */
class ReservationItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationItem::class);
    }

    public function getReservedQuantityForPeriod(
        int $productId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $blockingStatuses
    ): int {
        $qb = $this->createQueryBuilder('ri')
            ->select('COALESCE(SUM(ri.quantity), 0)')
            ->innerJoin('ri.reservation', 'r')
            ->where('IDENTITY(ri.product) = :pid')
            ->andWhere('r.status IN (:statuses)')
            // chevauchement de dates : start <= end2 AND end >= start2
            ->andWhere('r.startDate <= :end')
            ->andWhere('r.endDate >= :start')
            ->setParameter('pid', $productId)
            ->setParameter('statuses', $blockingStatuses)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }


    public function getReservedQuantityForProduct(
        Product $product,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): int {
        $qb = $this->createQueryBuilder('ri')
            ->select('COALESCE(SUM(ri.quantity), 0)')
            ->innerJoin('ri.reservation', 'r')
            ->andWhere('ri.product = :product')
            ->andWhere('r.status IN (:statuses)')
            // chevauchement de dates : [start,end] overlap [r.startDate,r.endDate]
            ->andWhere('r.startDate <= :end')
            ->andWhere('r.endDate >= :start')
            ->setParameter('product', $product)
            ->setParameter('statuses', Reservation::BLOCKING_STATUSES)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

}
