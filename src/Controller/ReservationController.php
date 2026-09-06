<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\CartService;
use App\Service\ReservationCreator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ReservationController extends AbstractController
{
    #[Route('/reservations', name: 'reservation_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $repo): Response
    {
        $user = $this->getUserOrDeny();

        $status = strtoupper((string) $request->query->get('status', 'ALL'));
        $sort   = (string) $request->query->get('sort', 'date_desc');

        $allReservations = $repo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $stats = [
            'ALL' => count($allReservations),
            'PENDING' => 0,
            'VALIDATED' => 0,
            'IN_PROGRESS' => 0,
            'COMPLETED' => 0,
        ];

        foreach ($allReservations as $reservation) {
            $rStatus = $reservation->getStatus();
            if (isset($stats[$rStatus])) {
                $stats[$rStatus]++;
            }
        }

        $allowedStatuses = ['ALL', 'PENDING', 'VALIDATED', 'IN_PROGRESS', 'COMPLETED'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'ALL';
        }

        $reservations = $allReservations;

        if ($status !== 'ALL') {
            $reservations = array_filter(
                $reservations,
                static fn ($r) => $r->getStatus() === $status
            );
        }

        usort($reservations, function ($a, $b) use ($sort) {
            return match ($sort) {
                'date_asc'   => $a->getCreatedAt() <=> $b->getCreatedAt(),
                'start_asc'  => $a->getStartDate() <=> $b->getStartDate(),
                'start_desc' => $b->getStartDate() <=> $a->getStartDate(),
                default      => $b->getCreatedAt() <=> $a->getCreatedAt(), // date_desc
            };
        });

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
            'stats' => $stats,
            'currentStatus' => $status,
            'currentSort' => $sort,
            'labels' => [
                'PENDING' => 'En attente',
                'VALIDATED' => 'Confirmée',
                'IN_PROGRESS' => 'En cours',
                'COMPLETED' => 'Terminée',
                'CANCELLED' => 'Annulée',
            ],
        ]);
    }

    #[Route('/reservation/checkout', name: 'reservation_checkout', methods: ['POST'])]
    public function checkout(CartService $cart, ReservationCreator $creator, Request $request): Response
    {
        $user = $this->getUserOrDeny();

        if (!$this->isCsrfTokenValid('checkout', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        try {
            $summary = $cart->getSummary();
            if (empty($summary['lines'])) {
                $this->addFlash('error', 'Votre panier est vide.');
                return $this->redirectToRoute('cart_index');
            }

            $reservation = $creator->createFromCart($user, $summary);

            $cart->clear();

            $this->addFlash('success', 'Réservation créée : ' . $reservation->getReference());
            return $this->redirectToRoute('reservation_index');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('cart_index');
        } catch (\Throwable) {
            $this->addFlash('error', 'Une erreur est survenue lors de la création de la réservation.');
            return $this->redirectToRoute('cart_index');
        }
    }

    #[Route('/reservations/{id}', name: 'reservation_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ReservationRepository $repo): Response
    {
        $user = $this->getUserOrDeny();

        $reservation = $repo->findOneForUserWithItems($id, $user);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    private function getUserOrDeny(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}

