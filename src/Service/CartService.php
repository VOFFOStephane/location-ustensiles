<?php

namespace App\Service;

use App\Service\AvailabilityService;
use App\Entity\ReservationItem;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CartService
{
    private const SESSION_KEY = 'cart_items';

    private SessionInterface $session;

    public function __construct(
        RequestStack $requestStack,
        private readonly ProductRepository $productRepository,
        private readonly ReservationCalculator $calculator,
        private readonly AvailabilityService $availability,
    ) {
        $session = $requestStack->getSession();
        if (!$session) {
            throw new \RuntimeException('Session non disponible. Active-la dans framework.yaml.');
        }
        $this->session = $session;
    }

    private function getCart(): array
    {
        return $this->session->get(self::SESSION_KEY, [
            'startDate' => null,
            'endDate'   => null,
            'items'     => [],
        ]);
    }

    private function saveCart(array $cart): void
    {
        $this->session->set(self::SESSION_KEY, $cart);
    }

    public function add(int $productId, int $quantity, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantité invalide');
        }

        // panier global
        $cart = $this->session->get(self::SESSION_KEY, [
            'startDate' => null,
            'endDate'   => null,
            'items'     => [],
        ]);

        $startStr = $start->format('Y-m-d');
        $endStr   = $end->format('Y-m-d');

        // Option A : dates globales obligatoires
        if (!empty($cart['startDate']) && !empty($cart['endDate'])) {
            if ($cart['startDate'] !== $startStr || $cart['endDate'] !== $endStr) {
                throw new \InvalidArgumentException(
                    "Ton panier est déjà pour {$cart['startDate']} → {$cart['endDate']}. Vide le panier pour changer les dates."
                );
            }
        } else {
            $cart['startDate'] = $startStr;
            $cart['endDate']   = $endStr;
        }

        // ✅ Vérification disponibilité (BD) sur la période
        $availableInDb = $this->availability->getAvailableQuantity($productId, $start, $end);

        // ✅ Quantité déjà dans le panier pour ce produit
        $alreadyInCart = $this->getQuantityInCartForProduct($cart, $productId);

        // ✅ Quantité totale demandée (panier + nouvelle demande)
        $wantedTotal = $alreadyInCart + $quantity;

        if ($wantedTotal > $availableInDb) {
            throw new \InvalidArgumentException(
                "Stock insuffisant sur ces dates : demandé {$wantedTotal}, disponible {$availableInDb}."
            );
        }

        // clé simple : un produit ne peut apparaître qu'une fois (dates globales)
        $key = (string) $productId;

        if (isset($cart['items'][$key])) {
            $cart['items'][$key]['quantity'] += $quantity;
        } else {
            $cart['items'][$key] = [
                'productId' => $productId,
                'quantity'  => $quantity,
            ];
        }

        $this->session->set(self::SESSION_KEY, $cart);
    }


    public function updateQuantity(string $key, int $quantity): void
    {
        $cart = $this->getCart();

        if (!isset($cart['items'][$key])) {
            return;
        }

        // supprimer si quantité <= 0
        if ($quantity <= 0) {
            unset($cart['items'][$key]);

            if (empty($cart['items'])) {
                $cart['startDate'] = null;
                $cart['endDate']   = null;
            }

            $this->saveCart($cart);
            return;
        }

        if (empty($cart['startDate']) || empty($cart['endDate'])) {
            throw new \RuntimeException('Panier corrompu : dates manquantes.');
        }

        $start = new \DateTimeImmutable($cart['startDate']);
        $end   = new \DateTimeImmutable($cart['endDate']);

        $productId = (int) $cart['items'][$key]['productId'];

        $currentQty = (int) $cart['items'][$key]['quantity'];

        // Stock dispo en BD (sans considérer le panier)
        $availableInDb = $this->availability->getAvailableQuantity($productId, $start, $end);

        // ✅ On autorise jusqu'à (stock restant + ce que moi j'avais déjà réservé dans le panier)
        $maxAllowed = $availableInDb + $currentQty;

        if ($quantity > $maxAllowed) {
            throw new \InvalidArgumentException(
                "Stock insuffisant sur ces dates : demandé {$quantity}, disponible {$maxAllowed}."
            );
        }

        $cart['items'][$key]['quantity'] = $quantity;

        $this->saveCart($cart);
    }


    public function remove(string $key): void
    {
        $cart = $this->getCart();

        unset($cart['items'][$key]);

        if (empty($cart['items'])) {
            $cart['startDate'] = null;
            $cart['endDate'] = null;
        }

        $this->saveCart($cart);
    }

    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    public function getSummary(): array
    {
        $cart = $this->getCart();

        $rentalTotal = '0.00';
        $depositTotal = '0.00';

        if (empty($cart['items'])) {
            return [
                'lines' => [],
                'rentalTotal' => $rentalTotal,
                'depositTotal' => $depositTotal,
                'grandTotalNow' => '0.00',
                'startDate' => null,
                'endDate' => null,
                'days' => 0,
            ];
        }

        // dates globales
        $start = new \DateTimeImmutable($cart['startDate']);
        $end   = new \DateTimeImmutable($cart['endDate']);
        $days  = $this->calculator->calculateDays($start, $end);

        $cartLines = [];
        $dirty = false;

        foreach ($cart['items'] as $key => $data) {
            $product = $this->productRepository->find($data['productId']);

            // si produit supprimé -> on nettoie la session
            if (!$product) {
                unset($cart['items'][$key]);
                $dirty = true;
                continue;
            }

            $qty = (int) $data['quantity'];

            $item = new ReservationItem();
            $item->setProduct($product);
            $item->setQuantity($qty);
            $item->setUnitPrice($product->getPricePerDay());
            $item->setUnitDeposit($product->getDepositUnit());

            $lineRental  = bcmul(bcmul($item->getUnitPrice(), (string) $qty, 2), (string) $days, 2);
            $lineDeposit = bcmul($item->getUnitDeposit(), (string) $qty, 2);

            $rentalTotal  = bcadd($rentalTotal, $lineRental, 2);
            $depositTotal = bcadd($depositTotal, $lineDeposit, 2);

            $cartLines[] = [
                'key' => (string) $key,
                'product' => $product,
                'quantity' => $qty,
                'startDate' => $start,
                'endDate' => $end,
                'days' => $days,
                'lineRentalTotal' => $lineRental,
                'lineDepositTotal' => $lineDeposit,
            ];
        }

        // si on a nettoyé des produits supprimés et que panier devient vide -> reset dates
        if ($dirty) {
            if (empty($cart['items'])) {
                $cart['startDate'] = null;
                $cart['endDate'] = null;
            }
            $this->saveCart($cart);
        }

        return [
            'lines' => $cartLines,
            'startDate' => $start,
            'endDate' => $end,
            'days' => $days,
            'rentalTotal' => $rentalTotal,
            'depositTotal' => $depositTotal,
            'grandTotalNow' => bcadd($rentalTotal, $depositTotal, 2),
        ];
    }

    public function getCartDates(): ?array
    {
        $cart = $this->getCart();

        if (empty($cart['startDate']) || empty($cart['endDate'])) {
            return null;
        }

        return [
            new \DateTimeImmutable($cart['startDate']),
            new \DateTimeImmutable($cart['endDate']),
        ];
    }

    private function getQuantityInCartForProduct(array $cart, int $productId): int
    {
        $key = (string) $productId;

        if (!isset($cart['items'][$key])) {
            return 0;
        }

        return (int) $cart['items'][$key]['quantity'];
    }

}

