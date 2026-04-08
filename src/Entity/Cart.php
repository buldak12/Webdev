<?php

namespace App\Entity;

use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\OneToOne(inversedBy: 'cart', targetEntity: User::class)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $items;

    #[ORM\ManyToOne(targetEntity: PromoCode::class)]
    private ?PromoCode $promoCode = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->expiresAt = (new \DateTime())->modify('+7 days');
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTime();
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

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(CartItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCart($this);
        }
        return $this;
    }

    public function removeItem(CartItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getCart() === $this) {
                $item->setCart(null);
            }
        }
        return $this;
    }

    public function findItemByVariant(ProductVariant $variant): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getVariant() === $variant) {
                return $item;
            }
        }
        return null;
    }

    public function clear(): static
    {
        $this->items->clear();
        $this->promoCode = null;
        return $this;
    }

    public function getPromoCode(): ?PromoCode
    {
        return $this->promoCode;
    }

    public function setPromoCode(?PromoCode $promoCode): static
    {
        $this->promoCode = $promoCode;
        return $this;
    }

    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->getQuantity();
        }
        return $count;
    }

    public function getSubtotal(): string
    {
        $subtotal = '0.00';
        foreach ($this->items as $item) {
            $subtotal = bcadd($subtotal, $item->getTotal(), 2);
        }
        return $subtotal;
    }

    public function getDiscount(): string
    {
        if (!$this->promoCode || !$this->promoCode->isValid()) {
            return '0.00';
        }
        return $this->promoCode->calculateDiscount($this->getSubtotal());
    }

    public function getTax(string $rate = '0.12'): string
    {
        $taxableAmount = bcsub($this->getSubtotal(), $this->getDiscount(), 2);
        return bcmul($taxableAmount, $rate, 2);
    }

    public function getTotal(): string
    {
        $subtotal = $this->getSubtotal();
        $discount = $this->getDiscount();
        return bcsub($subtotal, $discount, 2);
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function requiresAgeVerification(): bool
    {
        foreach ($this->items as $item) {
            if ($item->getVariant()->getProduct()->requiresAgeVerification()) {
                return true;
            }
        }
        return false;
    }
}
