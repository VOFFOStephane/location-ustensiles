<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\ReservationItem;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;



final class CartService
{
    private const SESSION_KEY = 'cart_items';

    private SessionInterface $session;

    public function __construct(
        RequestStack $requestStack,
        private readonly ProductRepository $productRepository,
        private readonly ReservationCalculator $calculator
    ) {
        $session = $requestStack->getSession();
        if (!$session) {
            throw new \RuntimeException('Session non disponible. Active-la dans framework.yaml.');
        }
        $this->session = $session;
    }


    /**
     * Un item = produit + quantité + dates.
     * On stocke des données scalaires en session.
     */
    public function add(int $productId, int $quantity, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantité invalide');
        }

        // clé unique (permet d’avoir le même produit avec d’autres dates)
        $key = $this->makeKey($productId, $start, $end);

        $items = $this->session->get(self::SESSION_KEY, []);

        if (isset($items[$key])) {
            $items[$key]['quantity'] += $quantity;
        } else {
            $items[$key] = [
                'productId' => $productId,
                'quantity'  => $quantity,
                'startDate' => $start->format('Y-m-d'),
                'endDate'   => $end->format('Y-m-d'),
            ];
        }

        $this->session->set(self::SESSION_KEY, $items);
    }

    public function updateQuantity(string $key, int $quantity): void
    {
        $items = $this->session->get(self::SESSION_KEY, []);
        if (!isset($items[$key])) return;

        if ($quantity <= 0) {
            unset($items[$key]);
        } else {
            $items[$key]['quantity'] = $quantity;
        }

        $this->session->set(self::SESSION_KEY, $items);
    }

    public function remove(string $key): void
    {
        $items = $this->session->get(self::SESSION_KEY, []);
        unset($items[$key]);
        $this->session->set(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    /**
     * Retourne une “vue” du panier avec produits hydratés + totaux calculés.
     * @throws \Exception
     */
    public function getSummary(): array
    {
        $raw = $this->session->get(self::SESSION_KEY, []);
        $cartLines = [];

        $rentalTotal = '0.00';
        $depositTotal = '0.00';

        foreach ($raw as $key => $data) {
            $product = $this->productRepository->find($data['productId']);
            if (!$product) {
                continue;
            }

            $start = new \DateTimeImmutable($data['startDate']);
            $end   = new \DateTimeImmutable($data['endDate']);
            $days  = $this->calculator->calculateDays($start, $end);

            // On utilise ReservationItem pour garder une logique uniforme
            $item = new ReservationItem();
            $item->setProduct($product);
            $item->setQuantity((int) $data['quantity']);
            $item->setUnitPrice($product->getPricePerDay());
            $item->setUnitDeposit($product->getDepositUnit());

            $lineRental  = bcmul(bcmul($item->getUnitPrice(), (string) $item->getQuantity(), 2), (string) $days, 2);
            $lineDeposit = bcmul($item->getUnitDeposit(), (string) $item->getQuantity(), 2);

            $rentalTotal = bcadd($rentalTotal, $lineRental, 2);
            $depositTotal = bcadd($depositTotal, $lineDeposit, 2);

            $cartLines[] = [
                'key' => $key,
                'product' => $product,
                'quantity' => $item->getQuantity(),
                'startDate' => $start,
                'endDate' => $end,
                'days' => $days,
                'lineRentalTotal' => $lineRental,
                'lineDepositTotal' => $lineDeposit,
            ];
        }

        return [
            'lines' => $cartLines,
            'rentalTotal' => $rentalTotal,
            'depositTotal' => $depositTotal,
            'grandTotalNow' => bcadd($rentalTotal, $depositTotal, 2), // si tu veux afficher “à payer maintenant” plus tard, on ajustera
        ];
    }

    private function makeKey(int $productId, \DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        return hash('sha256', $productId.'|'.$start->format('Y-m-d').'|'.$end->format('Y-m-d'));
    }
}

