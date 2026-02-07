<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Reservation;
use App\Repository\ProductRepository;
use App\Repository\ReservationItemRepository;

final class AvailabilityService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ReservationItemRepository $items
    ) {}

    public function getAvailableQuantity(int $productId, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $product = $this->products->find($productId);
        if (!$product) {
            throw new \InvalidArgumentException('Produit introuvable');
        }

        return $this->getAvailableQuantityForProduct($product, $start, $end);
    }

    public function getAvailableQuantityForProduct(Product $product, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $reserved = $this->items->getReservedQuantityForPeriod(
            $product->getId(),
            $start,
            $end,
            Reservation::BLOCKING_STATUSES ?? [
            Reservation::STATUS_PENDING,
            Reservation::STATUS_VALIDATED,
            Reservation::STATUS_IN_PROGRESS,
        ]
        );

        return max(0, $product->getQuantityTotal() - $reserved);
    }

    public function assertAvailable(int $productId, int $qty, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $product = $this->products->find($productId);
        if (!$product) {
            throw new \InvalidArgumentException('Produit introuvable');
        }

        $this->assertAvailableProduct($product, $qty, $start, $end);
    }

    public function assertAvailableProduct(Product $product, int $qty, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Quantité invalide.');
        }

        $available = $this->getAvailableQuantityForProduct($product, $start, $end);

        if ($qty > $available) {
            throw new \InvalidArgumentException(sprintf(
                'Stock insuffisant pour "%s" sur %s → %s : demandé %d, disponible %d.',
                $product->getName(),
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $qty,
                $available
            ));
        }
    }
}
