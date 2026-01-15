<?php

namespace App\Controller;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[IsGranted('ROLE_USER')]
final class CartController extends AbstractController
{
    /**
     * @throws \Exception
     */
    #[Route('/cart', name: 'cart_index', methods: ['GET'])]
    public function index(CartService $cart): Response
    {
        return $this->render('cart/index.html.twig', [
            'cart' => $cart->getSummary(),
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/cart/add/{id}', name: 'cart_add', methods: ['POST'])]
    public function add(int $id, Request $request, CartService $cart): Response
    {
        $start = new \DateTimeImmutable($request->request->get('startDate'));
        $end   = new \DateTimeImmutable($request->request->get('endDate'));
        $qty   = (int) $request->request->get('quantity', 1);

        $cart->add($id, $qty, $start, $end);

        $this->addFlash('success', 'Produit ajouté au panier');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/update/{key}', name: 'cart_update', methods: ['POST'])]
    public function update(string $key, Request $request, CartService $cart): Response
    {
        $qty = (int) $request->request->get('quantity', 1);
        $cart->updateQuantity($key, $qty);

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/remove/{key}', name: 'cart_remove', methods: ['POST'])]
    public function remove(string $key, CartService $cart): Response
    {
        $cart->remove($key);
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/clear', name: 'cart_clear', methods: ['POST'])]
    public function clear(CartService $cart): Response
    {
        $cart->clear();
        return $this->redirectToRoute('cart_index');
    }
}
