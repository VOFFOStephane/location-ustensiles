<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\ReservationDateChangeRequest;
use App\Entity\User;
use App\Form\ReservationDateChangeRequestType;
use App\Repository\ReservationDateChangeRequestRepository;
use App\Repository\ReservationRepository;
use App\Service\AvailabilityService;
use App\Service\ReservationCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ReservationDateChangeRequestController extends AbstractController
{
    private const MIN_DAYS_BEFORE_START = 10;

    #[Route('/reservations/{id}/change-request', name: 'reservation_change_request_new', methods: ['GET', 'POST'])]
    public function new(
        int $id,
        Request $request,
        ReservationRepository $reservations,
        ReservationDateChangeRequestRepository $requests,
        AvailabilityService $availability,
        ReservationCalculator $calculator,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $reservation = $reservations->findOneForUserWithItems($id, $user);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        // seulement si confirmé
        if ($reservation->getStatus() !== Reservation::STATUS_VALIDATED) {
            $this->addFlash('error', 'La modification de date est possible uniquement pour une réservation confirmée.');
            return $this->redirectToRoute('reservation_show', ['id' => $reservation->getId()]);
        }

        $today = new \DateTimeImmutable('today');
        $daysBeforeStart = (int) $today->diff($reservation->getStartDate())->format('%r%a');
        $canRequest = $daysBeforeStart >= self::MIN_DAYS_BEFORE_START;

        // Demande déjà en cours ?
        $pending = $requests->findPendingForReservation($reservation);

        $change = new ReservationDateChangeRequest();
        $change->setReservation($reservation);
        $change->setUser($user);

        // préremplissage
        $change->setNewStartDate($reservation->getStartDate());
        $change->setNewEndDate($reservation->getEndDate());

        $form = $this->createForm(ReservationDateChangeRequestType::class, $change);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {

            if ($pending) {
                $this->addFlash('warning', 'Une demande de modification est déjà en attente pour cette réservation.');
            } elseif (!$canRequest) {
                $this->addFlash('error', 'Délai non respecté : la demande doit être faite au moins 10 jours avant le retrait.');
            } elseif ($form->isValid()) {

                $start = $change->getNewStartDate();
                $end   = $change->getNewEndDate();

                // validations de base
                if ($start < $today || $end < $today) {
                    $this->addFlash('error', 'Les dates ne peuvent pas être dans le passé.');
                } elseif ($end < $start) {
                    $this->addFlash('error', 'La date de fin doit être après la date de début.');
                } else {

                    // ✅ vérifier stock pour chaque item, en excluant la réservation actuelle
                    foreach ($reservation->getItems() as $item) {
                        $pid = $item->getProduct()->getId();
                        $qty = $item->getQuantity();

                        $availableQty = $availability->getAvailableQuantityExcludingReservation(
                            $pid,
                            $start,
                            $end,
                            $reservation->getId()
                        );

                        if ($qty > $availableQty) {
                            $this->addFlash(
                                'error',
                                sprintf(
                                    'Stock insuffisant pour %s : demandé %d, dispo %d sur ces dates.',
                                    $item->getProduct()->getName(),
                                    $qty,
                                    $availableQty
                                )
                            );

                            // re-afficher le formulaire
                            return $this->render('reservation/change_request_new.html.twig', [
                                'reservation' => $reservation,
                                'form' => $form->createView(),
                                'pendingRequest' => $pending,
                                'canRequest' => $canRequest,
                                'daysBeforeStart' => $daysBeforeStart,
                                'minDays' => self::MIN_DAYS_BEFORE_START,
                                'minDate' => $today->format('Y-m-d'),
                            ]);
                        }
                    }

                    // ✅ figer l'ancien état + calculer le nouveau total et le delta
                    $change->setOldStartDate($reservation->getStartDate());
                    $change->setOldEndDate($reservation->getEndDate());
                    $change->setOldRentalTotal($reservation->getRentalTotal());

                    $days = $calculator->calculateDays($start, $end);

                    $newRental = '0.00';
                    foreach ($reservation->getItems() as $item) {
                        $line = bcmul(
                            bcmul($item->getUnitPrice(), (string) $item->getQuantity(), 2),
                            (string) $days,
                            2
                        );
                        $newRental = bcadd($newRental, $line, 2);
                    }

                    $change->setNewRentalTotal($newRental);
                    $change->setDeltaRentalTotal(bcsub($newRental, $reservation->getRentalTotal(), 2));
                    $change->setProcessedAt(null); // pas encore traité

                    $change->setStatus(ReservationDateChangeRequest::STATUS_PENDING);

                    $em->persist($change);
                    $em->flush();

                    $this->addFlash('success', 'Demande de modification envoyée ✅');
                    return $this->redirectToRoute('reservation_show', ['id' => $reservation->getId()]);
                }
            }
        }

        return $this->render('reservation/change_request_new.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
            'pendingRequest' => $pending,
            'canRequest' => $canRequest,
            'daysBeforeStart' => $daysBeforeStart,
            'minDays' => self::MIN_DAYS_BEFORE_START,
            'minDate' => $today->format('Y-m-d'),
        ]);
    }
}
