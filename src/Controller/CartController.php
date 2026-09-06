<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\AvailabilityService;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CartController extends AbstractController
{
    #[Route('/cart', name: 'cart_index', methods: ['GET'])]
    public function index(CartService $cart): Response
    {
        return $this->render('cart/index.html.twig', [
            'cart' => $cart->getSummary(),
        ]);
    }

    #[Route('/cart/add/{id}', name: 'cart_add', methods: ['POST'])]
    public function add(int $id, Request $request, CartService $cart): Response
    {
        if (!$this->isCsrfTokenValid('cart_add_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }

        $redirect = $request->headers->get('referer') ?? $this->generateUrl('cart_index');

        $startStr = $request->request->get('startDate');
        $endStr   = $request->request->get('endDate');

        if (!$startStr || !$endStr) {
            $this->addFlash('error', 'Dates manquantes.');
            return $this->redirect($redirect);
        }

        try {
            $start = new \DateTimeImmutable($startStr);
            $end   = new \DateTimeImmutable($endStr);
            $today = new \DateTimeImmutable('today');
        } catch (\Throwable) {
            $this->addFlash('error', 'Dates invalides.');
            return $this->redirect($redirect);
        }

        if ($start < $today) {
            $this->addFlash('error', 'La date de début ne peut pas être dans le passé.');
            return $this->redirect($redirect);
        }
        if ($end < $today) {
            $this->addFlash('error', 'La date de fin ne peut pas être dans le passé.');
            return $this->redirect($redirect);
        }

        if ($end < $start) {
            $this->addFlash('error', 'La date de fin doit être après la date de début.');
            return $this->redirect($redirect);
        }

        $qty = (int) $request->request->get('quantity', 1);
        if ($qty < 1) {
            $this->addFlash('error', 'Quantité invalide.');
            return $this->redirect($redirect);
        }
        if ($qty > 999) {
            $this->addFlash('error', 'Quantité trop élevée.');
            return $this->redirect($redirect);
        }

        try {
            // dispo vérifiée dans CartService
            $cart->add($id, $qty, $start, $end);
            $this->addFlash('success', 'Produit ajouté au panier');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable) {
            $this->addFlash('error', 'Erreur lors de l’ajout au panier.');
        }

        return $this->redirect($redirect);
    }

    #[Route('/cart/update/{key}', name: 'cart_update', methods: ['POST'])]
    public function update(string $key, Request $request, CartService $cart): Response
    {
        if (!$this->isCsrfTokenValid('cart_update_'.$key, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }

        $qty = (int) $request->request->get('quantity', 1);

        try {
            $cart->updateQuantity($key, $qty);
            $this->addFlash('success', 'Quantité mise à jour.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable) {
            $this->addFlash('error', 'Erreur lors de la mise à jour du panier.');
        }

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/remove/{key}', name: 'cart_remove', methods: ['POST'])]
    public function remove(string $key, Request $request, CartService $cart): Response
    {
        if (!$this->isCsrfTokenValid('cart_remove_'.$key, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }

        $cart->remove($key);
        $this->addFlash('success', 'Produit retiré du panier');

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/clear', name: 'cart_clear', methods: ['POST'])]
    public function clear(Request $request, CartService $cart): Response
    {
        if (!$this->isCsrfTokenValid('cart_clear', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }

        $cart->clear();
        $this->addFlash('success', 'Panier vidé');

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/test', name: 'cart_test', methods: ['GET'])]
    public function test(ProductRepository $repo): Response
    {
        return $this->render('cart/testpanier.html.twig', [
            'products' => $repo->findAll(),
        ]);
    }

    // ================== API AJAX ==================

    // ✅ A) dispo par ligne (sur dates globales du panier)
    #[Route('/cart/api/line/{key}/availability', name: 'cart_line_availability', methods: ['GET'])]
    public function lineAvailability(
        string $key,
        CartService $cart,
        AvailabilityService $availability
    ): JsonResponse {
        $summary = $cart->getSummary();

        if (empty($summary['lines'])) {
            return $this->json(['ok' => false, 'error' => 'Panier vide'], 400);
        }

        $productId = (int) $key;
        $start = $summary['startDate'];
        $end   = $summary['endDate'];

        if (!$start || !$end) {
            return $this->json(['ok' => false, 'error' => 'Dates panier manquantes'], 400);
        }

        $available = $availability->getAvailableQuantity($productId, $start, $end);

        $currentQty = 0;
        foreach ($summary['lines'] as $line) {
            if ((string) $line['key'] === $key) {
                $currentQty = (int) $line['quantity'];
                break;
            }
        }

        return $this->json([
            'ok' => true,
            'key' => $key,
            'productId' => $productId,
            'available' => $available,
            'currentQty' => $currentQty,
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
        ]);
    }

    // ✅ B) update quantité en AJAX (retourne ligne + totaux)
    #[Route('/cart/api/line/{key}/quantity', name: 'cart_line_update_quantity', methods: ['POST'])]
    public function updateQuantityAjax(
        string $key,
        Request $request,
        CartService $cart,
        AvailabilityService $availability
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('cart_update_'.$key, (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'CSRF invalide'], 403);
        }

        $qty = (int) $request->request->get('quantity', 1);

        try {
            $cart->updateQuantity($key, $qty);
            $summary = $cart->getSummary();

            if (empty($summary['lines'])) {
                return $this->json(['ok' => true, 'cartEmpty' => true]);
            }

            $productId = (int) $key;
            $start = $summary['startDate'];
            $end   = $summary['endDate'];

            $available = $availability->getAvailableQuantity($productId, $start, $end);

            $linePayload = null;
            foreach ($summary['lines'] as $line) {
                if ((string) $line['key'] === $key) {
                    $linePayload = [
                        'key' => (string) $line['key'],
                        'productId' => $line['product']->getId(),
                        'productName' => $line['product']->getName(),
                        'quantity' => (int) $line['quantity'],
                        'lineRentalTotal' => $line['lineRentalTotal'],
                        'lineDepositTotal' => $line['lineDepositTotal'],
                        'days' => (int) $line['days'],
                        'startDate' => $start->format('Y-m-d'),
                        'endDate' => $end->format('Y-m-d'),
                    ];
                    break;
                }
            }

            $breakdown = $this->paymentBreakdown($summary['rentalTotal'], $summary['depositTotal']);

            return $this->json([
                'ok' => true,
                'available' => $available,
                'line' => $linePayload,
                'totals' => [
                    'rentalTotal' => $summary['rentalTotal'],
                    'depositTotal' => $summary['depositTotal'],
                    'days' => $summary['days'],
                    'startDate' => $summary['startDate']?->format('Y-m-d'),
                    'endDate' => $summary['endDate']?->format('Y-m-d'),
                ],
                'payment' => $breakdown,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'error' => 'Erreur serveur.'], 500);
        }
    }

    // ✅ C) update des dates globales (Option A) en AJAX
    /* #[Route('/cart/api/dates', name: 'cart_update_dates', methods: ['POST'])]
    public function updateDatesAjax(
        Request $request,
        CartService $cart
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('cart_dates', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'CSRF invalide'], 403);
        }

        $startStr = $request->request->get('startDate');
        $endStr   = $request->request->get('endDate');

        if (!$startStr || !$endStr) {
            return $this->json(['ok' => false, 'error' => 'Dates manquantes'], 400);
        }

        try {
            $start = new \DateTimeImmutable($startStr);
            $end   = new \DateTimeImmutable($endStr);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'error' => 'Dates invalides'], 400);
        }

        if ($end < $start) {
            return $this->json(['ok' => false, 'error' => 'La fin doit être après le début'], 400);
        }

        try {
            // ⚠️ nécessite CartService::updateDates()
            $cart->updateDates($start, $end);
            $summary = $cart->getSummary();

            $breakdown = $this->paymentBreakdown($summary['rentalTotal'], $summary['depositTotal']);

            $lines = [];
            foreach ($summary['lines'] as $line) {
                $lines[] = [
                    'key' => (string) $line['key'],
                    'quantity' => (int) $line['quantity'],
                    'lineRentalTotal' => $line['lineRentalTotal'],
                    'lineDepositTotal' => $line['lineDepositTotal'],
                    'days' => (int) $line['days'],
                    'startDate' => $summary['startDate']?->format('Y-m-d'),
                    'endDate' => $summary['endDate']?->format('Y-m-d'),
                ];
            }

            return $this->json([
                'ok' => true,
                'dates' => [
                    'startDate' => $summary['startDate']?->format('Y-m-d'),
                    'endDate' => $summary['endDate']?->format('Y-m-d'),
                    'days' => $summary['days'],
                ],
                'totals' => [
                    'rentalTotal' => $summary['rentalTotal'],
                    'depositTotal' => $summary['depositTotal'],
                ],
                'payment' => $breakdown,
                'lines' => $lines,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'error' => 'Erreur serveur.'], 500);
        }
    } */

    // ✅ Paiement : caution payée plus tard (comme tu l’as demandé)
    // - acompte (30% location) = à payer maintenant
    // - solde location + caution = à payer plus tard
    private function paymentBreakdown(string $rentalTotal, string $depositTotal): array
    {
        // bcmul/bcsub/bcadd => cohérent avec tes DECIMAL en string
        $acompte = bcmul($rentalTotal, '0.30', 2);
        $solde   = bcsub($rentalTotal, $acompte, 2);

        $payNow   = $acompte;
        $payLater = bcadd($solde, $depositTotal, 2);

        return [
            'acompte' => $acompte,
            'solde' => $solde,
            'payNow' => $payNow,
            'payLater' => $payLater,
        ];
    }
}
