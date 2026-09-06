<?php

namespace App\Entity;

use App\Repository\ReservationDateChangeRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationDateChangeRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ReservationDateChangeRequest
{
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dateChangeRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // ===== Dates demandées par le client =====
    #[ORM\Column]
    private ?\DateTimeImmutable $newStartDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $newEndDate = null;

    // ===== Snapshot (historique) =====
    #[ORM\Column]
    private ?\DateTimeImmutable $oldStartDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $oldEndDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $oldRentalTotal = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $newRentalTotal = '0.00';

    // new - old (positif = supplément à payer sur place / négatif = à déduire-rembourser)
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $deltaRentalTotal = '0.00';

    // Date de traitement par l'admin
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    // ===== Statut / messages =====
    #[ORM\Column(length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminMessage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;

        if (!$this->status) {
            $this->status = self::STATUS_PENDING;
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ===== Getters / Setters =====

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getNewStartDate(): ?\DateTimeImmutable
    {
        return $this->newStartDate;
    }

    public function setNewStartDate(\DateTimeImmutable $newStartDate): static
    {
        $this->newStartDate = $newStartDate;
        return $this;
    }

    public function getNewEndDate(): ?\DateTimeImmutable
    {
        return $this->newEndDate;
    }

    public function setNewEndDate(\DateTimeImmutable $newEndDate): static
    {
        $this->newEndDate = $newEndDate;
        return $this;
    }

    public function getOldStartDate(): ?\DateTimeImmutable
    {
        return $this->oldStartDate;
    }

    public function setOldStartDate(\DateTimeImmutable $oldStartDate): static
    {
        $this->oldStartDate = $oldStartDate;
        return $this;
    }

    public function getOldEndDate(): ?\DateTimeImmutable
    {
        return $this->oldEndDate;
    }

    public function setOldEndDate(\DateTimeImmutable $oldEndDate): static
    {
        $this->oldEndDate = $oldEndDate;
        return $this;
    }

    public function getOldRentalTotal(): string
    {
        return $this->oldRentalTotal;
    }

    public function setOldRentalTotal(string $oldRentalTotal): static
    {
        $this->oldRentalTotal = $oldRentalTotal;
        return $this;
    }

    public function getNewRentalTotal(): string
    {
        return $this->newRentalTotal;
    }

    public function setNewRentalTotal(string $newRentalTotal): static
    {
        $this->newRentalTotal = $newRentalTotal;
        return $this;
    }

    public function getDeltaRentalTotal(): string
    {
        return $this->deltaRentalTotal;
    }

    public function setDeltaRentalTotal(string $deltaRentalTotal): static
    {
        $this->deltaRentalTotal = $deltaRentalTotal;
        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getAdminMessage(): ?string
    {
        return $this->adminMessage;
    }

    public function setAdminMessage(?string $adminMessage): static
    {
        $this->adminMessage = $adminMessage;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

