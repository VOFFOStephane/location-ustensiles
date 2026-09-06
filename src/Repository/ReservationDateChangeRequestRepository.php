<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\ReservationDateChangeRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationDateChangeRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationDateChangeRequest::class);
    }

    public function hasPendingForReservation(Reservation $reservation): bool
    {
        $count = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.reservation = :r')
            ->andWhere('d.status = :s')
            ->setParameter('r', $reservation)
            ->setParameter('s', ReservationDateChangeRequest::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findPendingForReservation(Reservation $reservation): ?ReservationDateChangeRequest
    {
        return $this->findOneBy(
            ['reservation' => $reservation, 'status' => ReservationDateChangeRequest::STATUS_PENDING],
            ['createdAt' => 'DESC']
        );
    }

    public function findLatestForReservation(Reservation $reservation): ?ReservationDateChangeRequest
    {
        return $this->findOneBy(
            ['reservation' => $reservation],
            ['createdAt' => 'DESC']
        );
    }
}
