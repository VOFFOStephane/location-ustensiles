<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\AvailabilityService;

readonly final class ReservationCreator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationReferenceGenerator $refGenerator,
        private readonly ReservationCalculator $calculator,
        private readonly AvailabilityService $availability
    ) {}

    /**
     * $cartSummary = résultat de CartService::getSummary()
     */
    public function createFromCart(User $user, array $cartSummary): Reservation
    {
        $lines = $cartSummary['lines'] ?? [];
        if (count($lines) === 0) {
            throw new \InvalidArgumentException('Panier vide.');
        }

        $start = $lines[0]['startDate'];
        $end   = $lines[0]['endDate'];

        foreach ($lines as $line) {
            if ($line['startDate'] != $start || $line['endDate'] != $end) {
                throw new \InvalidArgumentException(
                    'Toutes les lignes du panier doivent avoir les mêmes dates. Fais 2 réservations séparées.'
                );
            }
        }

        return $this->em->wrapInTransaction(function () use ($user, $lines, $start, $end): Reservation {

            // 1) Vérification stock AVANT création (et dans la transaction)
            foreach ($lines as $line) {
                $product = $line['product'];
                $qty = (int) $line['quantity'];

                $productId = (int) $product->getId();
                if ($productId <= 0) {
                    throw new \RuntimeException('Produit invalide (ID manquant).');
                }

                $this->availability->assertAvailable($productId, $qty, $start, $end);
            }

            $days = $this->calculator->calculateDays($start, $end);
            $returnDue = $end->modify('+1 day');

            $reservation = new Reservation();
            $reservation->setUser($user);
            $reservation->setReference($this->refGenerator->generate());
            $reservation->setStatus(Reservation::STATUS_PENDING);
            $reservation->setStartDate($start);
            $reservation->setEndDate($end);
            $reservation->setReturnDueDate($returnDue);
            $reservation->setArchived(false);

            $rentalTotal = '0.00';
            $depositTotal = '0.00';

            foreach ($lines as $line) {
                $product = $line['product'];
                $qty = (int) $line['quantity'];

                $item = new ReservationItem();
                $item->setProduct($product);
                $item->setQuantity($qty);

                // On fige les prix au moment de la réservation
                $item->setUnitPrice($product->getPricePerDay());
                $item->setUnitDeposit($product->getDepositUnit());

                $lineRental = bcmul(
                    bcmul($item->getUnitPrice(), (string) $qty, 2),
                    (string) $days,
                    2
                );
                $lineDeposit = bcmul($item->getUnitDeposit(), (string) $qty, 2);

                $item->setLineRentalTotal($lineRental);
                $item->setLineDepositTotal($lineDeposit);

                $rentalTotal = bcadd($rentalTotal, $lineRental, 2);
                $depositTotal = bcadd($depositTotal, $lineDeposit, 2);

                $reservation->addItem($item);
            }

            $reservation->setRentalTotal($rentalTotal);
            $reservation->setDepositTotal($depositTotal);

            $this->em->persist($reservation);
            $this->em->flush();

            return $reservation;
        });
    }
}
