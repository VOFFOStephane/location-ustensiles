<?php

namespace App\Service;

use App\Entity\ReservationDateChangeRequest;
use Doctrine\ORM\EntityManagerInterface;

final class ChangeRequestApplier
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationCalculator $calculator,
        private readonly AvailabilityService $availability
    ) {}

    public function approve(ReservationDateChangeRequest $req): void
    {
        $reservation = $req->getReservation();
        $start = $req->getNewStartDate();
        $end   = $req->getNewEndDate();

        if (!$reservation || !$start || !$end) {
            throw new \InvalidArgumentException('Demande invalide (reservation/dates manquantes).');
        }

        if ($req->getStatus() !== ReservationDateChangeRequest::STATUS_PENDING) {
            throw new \InvalidArgumentException('Cette demande n’est pas en attente.');
        }

        // Re-check stock au moment de l’approbation
        foreach ($reservation->getItems() as $item) {
            $pid = $item->getProduct()->getId();
            $qty = $item->getQuantity();

            $available = $this->availability->getAvailableQuantityExcludingReservation(
                $pid, $start, $end, $reservation->getId()
            );

            if ($qty > $available) {
                throw new \InvalidArgumentException(
                    "Stock insuffisant pour {$item->getProduct()->getName()} sur ces dates."
                );
            }
        }

        // Recalcul location
        $days = $this->calculator->calculateDays($start, $end);

        $newRentalTotal = '0.00';
        foreach ($reservation->getItems() as $item) {
            $lineRental = bcmul(
                bcmul($item->getUnitPrice(), (string) $item->getQuantity(), 2),
                (string) $days,
                2
            );
            $item->setLineRentalTotal($lineRental);
            $newRentalTotal = bcadd($newRentalTotal, $lineRental, 2);
        }

        // Historique (sécurité si pas déjà rempli)
        if ($req->getOldStartDate() === null) $req->setOldStartDate($reservation->getStartDate());
        if ($req->getOldEndDate() === null)   $req->setOldEndDate($reservation->getEndDate());
        $req->setOldRentalTotal($req->getOldRentalTotal() ?: $reservation->getRentalTotal());

        $req->setNewRentalTotal($newRentalTotal);
        $req->setDeltaRentalTotal(bcsub($newRentalTotal, $reservation->getRentalTotal(), 2));
        $req->setProcessedAt(new \DateTimeImmutable());
        $req->setStatus(ReservationDateChangeRequest::STATUS_APPROVED);

        // Appliquer sur réservation
        $reservation->setStartDate($start);
        $reservation->setEndDate($end);
        $reservation->setReturnDueDate($end->modify('+1 day'));
        $reservation->setRentalTotal($newRentalTotal);

        $this->em->flush();
    }

    public function reject(ReservationDateChangeRequest $req, ?string $adminMessage = null): void
    {
        if ($req->getStatus() !== ReservationDateChangeRequest::STATUS_PENDING) {
            throw new \InvalidArgumentException('Cette demande n’est pas en attente.');
        }

        $req->setStatus(ReservationDateChangeRequest::STATUS_REJECTED);
        $req->setProcessedAt(new \DateTimeImmutable());
        $req->setAdminMessage($adminMessage);

        $this->em->flush();
    }
}
