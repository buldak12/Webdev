<?php

namespace App\Entity;

use App\Repository\ProductVariantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProductVariant
{
    // Common nicotine strengths for vape products
    public const NICOTINE_0MG = '0mg';
    public const NICOTINE_3MG = '3mg';
    public const NICOTINE_6MG = '6mg';
    public const NICOTINE_12MG = '12mg';
    public const NICOTINE_18MG = '18mg';
    public const NICOTINE_25MG = '25mg';
    public const NICOTINE_50MG = '50mg';

    public const NICOTINE_STRENGTHS = [
        self::NICOTINE_0MG => '0mg (Nicotine Free)',
        self::NICOTINE_3MG => '3mg',
        self::NICOTINE_6MG => '6mg',
        self::NICOTINE_12MG => '12mg',
        self::NICOTINE_18MG => '18mg',
        self::NICOTINE_25MG => '25mg (Salt Nic)',
        self::NICOTINE_50MG => '50mg (Salt Nic)',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $sku = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $flavor = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $nicotineStrength = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $size = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $priceModifier = '0.00';

    #[ORM\Column]
    private int $stock = 0;

    #[ORM\Column]
    private int $lowStockThreshold = 10;

    #[ORM\Column]
    private int $reservedStock = 0;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): static
    {
        $this->sku = $sku;
        return $this;
    }

    public function getFlavor(): ?string
    {
        return $this->flavor;
    }

    public function setFlavor(?string $flavor): static
    {
        $this->flavor = $flavor;
        return $this;
    }

    public function getNicotineStrength(): ?string
    {
        return $this->nicotineStrength;
    }

    public function setNicotineStrength(?string $nicotineStrength): static
    {
        $this->nicotineStrength = $nicotineStrength;
        return $this;
    }

    public function getNicotineStrengthLabel(): string
    {
        return self::NICOTINE_STRENGTHS[$this->nicotineStrength] ?? $this->nicotineStrength ?? '';
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(?string $size): static
    {
        $this->size = $size;
        return $this;
    }

    public function getPriceModifier(): string
    {
        return $this->priceModifier;
    }

    public function setPriceModifier(string $priceModifier): static
    {
        $this->priceModifier = $priceModifier;
        return $this;
    }

    public function getPrice(): string
    {
        if (!$this->product) {
            return $this->priceModifier;
        }
        return bcadd($this->product->getBasePrice(), $this->priceModifier, 2);
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = max(0, $stock);
        return $this;
    }

    public function addStock(int $quantity): static
    {
        $this->stock += $quantity;
        return $this;
    }

    public function removeStock(int $quantity): static
    {
        $this->stock = max(0, $this->stock - $quantity);
        return $this;
    }

    public function getLowStockThreshold(): int
    {
        return $this->lowStockThreshold;
    }

    public function setLowStockThreshold(int $lowStockThreshold): static
    {
        $this->lowStockThreshold = $lowStockThreshold;
        return $this;
    }

    public function isLowStock(): bool
    {
        return $this->getAvailableStock() <= $this->lowStockThreshold;
    }

    public function getReservedStock(): int
    {
        return $this->reservedStock;
    }

    public function setReservedStock(int $reservedStock): static
    {
        $this->reservedStock = $reservedStock;
        return $this;
    }

    public function reserveStock(int $quantity): bool
    {
        if ($this->getAvailableStock() < $quantity) {
            return false;
        }
        $this->reservedStock += $quantity;
        return true;
    }

    public function releaseReservedStock(int $quantity): static
    {
        $this->reservedStock = max(0, $this->reservedStock - $quantity);
        return $this;
    }

    public function confirmReservedStock(int $quantity): static
    {
        $this->stock -= $quantity;
        $this->reservedStock = max(0, $this->reservedStock - $quantity);
        return $this;
    }

    public function getAvailableStock(): int
    {
        return $this->stock - $this->reservedStock;
    }

    public function isInStock(): bool
    {
        return $this->getAvailableStock() > 0;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        return $this;
    }

    public function getDisplayName(): string
    {
        $parts = [];
        
        if ($this->product) {
            $parts[] = $this->product->getName();
        }
        
        if ($this->flavor) {
            $parts[] = $this->flavor;
        }
        
        if ($this->nicotineStrength) {
            $parts[] = $this->nicotineStrength;
        }
        
        if ($this->size) {
            $parts[] = $this->size;
        }
        
        return implode(' - ', $parts);
    }

    public function getVariantAttributes(): string
    {
        $attrs = [];
        
        if ($this->flavor) {
            $attrs[] = $this->flavor;
        }
        
        if ($this->nicotineStrength) {
            $attrs[] = $this->nicotineStrength;
        }
        
        if ($this->size) {
            $attrs[] = $this->size;
        }
        
        return implode(' / ', $attrs);
    }
}
