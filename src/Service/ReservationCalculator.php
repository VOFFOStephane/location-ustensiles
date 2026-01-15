<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Reservation;
use App\Entity\ReservationItem;

final class ReservationCalculator
{
    /**
     * Règle simple : minimum 1 jour de location.
     */
    public function calculateDays(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $diff = $start->diff($end);

        // +1 si tu veux compter "jours calendaires" (ex: 10->12 = 2 jours)
        // Ici, on fait 10->12 => 2 jours (standard location)
        $days = (int) $diff->days;

        return max(1, $days);
    }

    /**
     * Calcule les totaux d'une réservation à partir de ses items et dates,
     * et écrit les résultats dans Reservation + ReservationItem.
     */
    public function computeReservationTotals(Reservation $reservation): void
    {
        $start = $reservation->getStartDate();
        $end = $reservation->getEndDate();

        if (!$start || !$end) {
            throw new \InvalidArgumentException('StartDate et EndDate sont obligatoires pour calculer une réservation.');
        }

        $days = $this->calculateDays($start, $end);

        $rentalTotal = '0.00';
        $depositTotal = '0.00';

        foreach ($reservation->getItems() as $item) {
            $product = $item->getProduct();
            if (!$product) {
                throw new \InvalidArgumentException('Chaque ReservationItem doit avoir un Product.');
            }

            $qty = $item->getQuantity();
            if ($qty <= 0) {
                throw new \InvalidArgumentException('La quantité doit être > 0.');
            }

            // Prix et caution figés (si pas encore figés)
            if ($item->getUnitPrice() === null) {
                $item->setUnitPrice($product->getPricePerDay());
            }
            if ($item->getUnitDeposit() === null) {
                $item->setUnitDeposit($product->getDepositUnit());
            }

            // Totaux ligne
            $lineRental = bcmul(bcmul($item->getUnitPrice(), (string) $qty, 2), (string) $days, 2);
            $lineDeposit = bcmul($item->getUnitDeposit(), (string) $qty, 2);

            $item->setLineRentalTotal($lineRental);
            $item->setLineDepositTotal($lineDeposit);

            // Totaux réservation
            $rentalTotal = bcadd($rentalTotal, $lineRental, 2);
            $depositTotal = bcadd($depositTotal, $lineDeposit, 2);
        }

        $reservation->setRentalTotal($rentalTotal);
        $reservation->setDepositTotal($depositTotal);

        // Exemple : returnDueDate = endDate + 1 jour (tu peux ajuster plus tard)
        if ($reservation->getReturnDueDate() === null) {
            $reservation->setReturnDueDate($end->modify('+1 day'));
        }
    }
}
