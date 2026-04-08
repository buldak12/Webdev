<?php

namespace App\Entity;

use App\Repository\LoyaltyTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoyaltyTransactionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LoyaltyTransaction
{
    public const TYPE_EARNED = 'earned';
    public const TYPE_REDEEMED = 'redeemed';
    public const TYPE_EXPIRED = 'expired';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_EARNED => 'Points Earned',
        self::TYPE_REDEEMED => 'Points Redeemed',
        self::TYPE_EXPIRED => 'Points Expired',
        self::TYPE_ADJUSTMENT => 'Manual Adjustment',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_EARNED;

    #[ORM\Column]
    private int $points = 0;

    #[ORM\Column]
    private int $balanceAfter = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $referenceType = null;

    #[ORM\Column(nullable: true)]
    private ?int $referenceId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;
        return $this;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(int $balanceAfter): static
    {
        $this->balanceAfter = $balanceAfter;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function setReferenceType(?string $referenceType): static
    {
        $this->referenceType = $referenceType;
        return $this;
    }

    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }

    public function setReferenceId(?int $referenceId): static
    {
        $this->referenceId = $referenceId;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
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

    public function isEarned(): bool
    {
        return $this->type === self::TYPE_EARNED;
    }

    public function isRedeemed(): bool
    {
        return $this->type === self::TYPE_REDEEMED;
    }

    public static function createEarned(User $user, int $points, string $description, ?string $refType = null, ?int $refId = null): self
    {
        $transaction = new self();
        $transaction->setUser($user);
        $transaction->setType(self::TYPE_EARNED);
        $transaction->setPoints($points);
        $transaction->setDescription($description);
        $transaction->setReferenceType($refType);
        $transaction->setReferenceId($refId);
        $transaction->setBalanceAfter($user->getLoyaltyPoints() + $points);
        return $transaction;
    }

    public static function createRedeemed(User $user, int $points, string $description, ?string $refType = null, ?int $refId = null): self
    {
        $transaction = new self();
        $transaction->setUser($user);
        $transaction->setType(self::TYPE_REDEEMED);
        $transaction->setPoints($points);
        $transaction->setDescription($description);
        $transaction->setReferenceType($refType);
        $transaction->setReferenceId($refId);
        $transaction->setBalanceAfter($user->getLoyaltyPoints() - $points);
        return $transaction;
    }
}
