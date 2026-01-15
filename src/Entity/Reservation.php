<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
#[ORM\UniqueConstraint(name: 'uniq_reservation_reference', columns: ['reference'])]
#[ORM\HasLifecycleCallbacks]
class Reservation
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VALIDATED = 'VALIDATED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $reference = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $returnDueDate = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $rentalTotal = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $depositTotal = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $archived = false;




    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;




    /**
     * @var Collection<int, ReservationItem>
     */
    #[ORM\OneToMany(
        targetEntity: ReservationItem::class,
        mappedBy: 'reservation',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $items;


    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getReturnDueDate(): ?\DateTimeImmutable
    {
        return $this->returnDueDate;
    }

    public function setReturnDueDate(\DateTimeImmutable $returnDueDate): static
    {
        $this->returnDueDate = $returnDueDate;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRentalTotal(): ?string
    {
        return $this->rentalTotal;
    }

    public function setRentalTotal(string $rentalTotal): static
    {
        $this->rentalTotal = $rentalTotal;

        return $this;
    }

    public function getDepositTotal(): ?string
    {
        return $this->depositTotal;
    }

    public function setDepositTotal(string $depositTotal): static
    {
        $this->depositTotal = $depositTotal;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): static
    {
        $this->archived = $archived;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;

        // valeur par défaut utile
        if ($this->status === null) {
            $this->status = self::STATUS_PENDING;
        }

    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, ReservationItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ReservationItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setReservation($this);
        }

        return $this;
    }

    public function removeItem(ReservationItem $item): static
    {
        $this->items->removeElement($item);
            // orphanRemoval=true va supprimer l'item, pas besoin de nuller la FK
        return $this;
    }
    //fonctions helpers
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }


}
