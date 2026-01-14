<?php

namespace App\Entity;

use App\Repository\ReservationItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationItemRepository::class)]
#[ORM\Table(
    name: 'reservation_item',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_reservation_product', columns: ['reservation_id', 'product_id'])
    ]
)]
class ReservationItem

{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $quantity = 1;


    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $unitPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $unitDeposit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $lineRentalTotal = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $lineDepositTotal = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }


    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getUnitDeposit(): ?string
    {
        return $this->unitDeposit;
    }

    public function setUnitDeposit(string $unitDeposit): static
    {
        $this->unitDeposit = $unitDeposit;

        return $this;
    }

    public function getLineRentalTotal(): ?string
    {
        return $this->lineRentalTotal;
    }

    public function setLineRentalTotal(string $lineRentalTotal): static
    {
        $this->lineRentalTotal = $lineRentalTotal;

        return $this;
    }

    public function getLineDepositTotal(): ?string
    {
        return $this->lineDepositTotal;
    }

    public function setLineDepositTotal(string $lineDepositTotal): static
    {
        $this->lineDepositTotal = $lineDepositTotal;

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }
}
