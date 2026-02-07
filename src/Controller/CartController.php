<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        } catch (\Throwable) {
            $this->addFlash('error', 'Dates invalides.');
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
            // ✅ La disponibilité est déjà vérifiée dans CartService
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
}
