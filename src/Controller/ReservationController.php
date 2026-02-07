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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


#[IsGranted('ROLE_USER')]
final class ReservationController extends AbstractController
{
    #[Route('/reservations', name: 'reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $repo): Response
    {
        $user = $this->getUserOrDeny();

        $reservations = $repo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
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
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la création de la réservation.');
            return $this->redirectToRoute('cart_index');
        }
    }

    #[Route('/reservations/{id}', name: 'reservation_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ReservationRepository $repo): Response
    {
        $user = $this->getUserOrDeny();

        // On charge la réservation + items + produits en une requête
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


