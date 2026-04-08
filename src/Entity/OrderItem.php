<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $unitPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\Column(length: 255)]
    private ?string $productName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $variantSku = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $variantAttributes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    private ?ProductVariant $variant = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->calculateTotal();
    }

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
        $this->quantity = max(1, $quantity);
        $this->calculateTotal();
        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        $this->calculateTotal();
        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function getSubtotal(): string
    {
        return $this->total ?? '0.00';
    }

    public function getPrice(): ?string
    {
        return $this->getUnitPrice();
    }

    private function calculateTotal(): void
    {
        if ($this->unitPrice !== null) {
            $this->total = bcmul($this->unitPrice, (string) $this->quantity, 2);
        }
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;
        return $this;
    }

    public function getVariantSku(): ?string
    {
        return $this->variantSku;
    }

    public function setVariantSku(?string $variantSku): static
    {
        $this->variantSku = $variantSku;
        return $this;
    }

    public function getVariantAttributes(): ?string
    {
        return $this->variantAttributes;
    }

    public function getVariantName(): ?string
    {
        return $this->variantAttributes;
    }

    public function setVariantAttributes(?string $variantAttributes): static
    {
        $this->variantAttributes = $variantAttributes;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getVariant(): ?ProductVariant
    {
        return $this->variant;
    }

    public function setVariant(?ProductVariant $variant): static
    {
        $this->variant = $variant;
        
        if ($variant) {
            $this->variantSku = $variant->getSku();
            $this->variantAttributes = $variant->getVariantAttributes();
            $this->unitPrice = $variant->getPrice();
            $this->productName = $variant->getProduct()?->getName() ?? '';
        }
        
        return $this;
    }

    public static function createFromCartItem(CartItem $cartItem): self
    {
        $orderItem = new self();
        $orderItem->setVariant($cartItem->getVariant());
        $orderItem->setQuantity($cartItem->getQuantity());
        $orderItem->setUnitPrice($cartItem->getUnitPrice());
        return $orderItem;
    }
}
