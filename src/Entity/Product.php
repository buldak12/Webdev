<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Product
{
    private const LEGACY_MAIN_IMAGE_MAP = [
        'images/shop-product-2-300x300.png' => 'images/mango-tago.webp',
        'images/shop-product-3-300x300.png' => 'images/BLUE-RAZZ-ICE.webp',
        'images/shop-product-4-300x300.png' => 'images/strawberry milk.webp',
        'images/shop-product-5-300x300.png' => 'images/Salt Nic.jpg',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 280, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $basePrice = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $sku = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mainImage = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $images = [];

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $requiresAgeVerification = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $variants;

    public function __construct()
    {
        $this->variants = new ArrayCollection();
    }

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    public function getBasePrice(): ?string
    {
        return $this->basePrice;
    }

    public function setBasePrice(string $basePrice): static
    {
        $this->basePrice = $basePrice;
        return $this;
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

    public function getMainImage(): ?string
    {
        return $this->normalizeMainImagePath($this->mainImage);
    }

    public function setMainImage(?string $mainImage): static
    {
        $this->mainImage = $this->normalizeMainImagePath($mainImage);
        return $this;
    }

    public function getImages(): ?array
    {
        return $this->images;
    }

    public function setImages(?array $images): static
    {
        $this->images = $images;
        return $this;
    }

    public function addImage(string $image): static
    {
        if (!in_array($image, $this->images ?? [], true)) {
            $this->images[] = $image;
        }
        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $this->brand = $brand;
        return $this;
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

    public function requiresAgeVerification(): bool
    {
        return $this->requiresAgeVerification;
    }

    public function setRequiresAgeVerification(bool $requiresAgeVerification): static
    {
        $this->requiresAgeVerification = $requiresAgeVerification;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function getActiveVariants(): Collection
    {
        return $this->variants->filter(fn(ProductVariant $variant) => $variant->isActive());
    }

    public function addVariant(ProductVariant $variant): static
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setProduct($this);
        }
        return $this;
    }

    public function removeVariant(ProductVariant $variant): static
    {
        if ($this->variants->removeElement($variant)) {
            if ($variant->getProduct() === $this) {
                $variant->setProduct(null);
            }
        }
        return $this;
    }

    private function normalizeMainImagePath(?string $mainImage): ?string
    {
        if ($mainImage === null) {
            return null;
        }

        $normalized = trim($mainImage);
        if ($normalized == '') {
            return null;
        }

        if (preg_match('#^https?://#i', $normalized) === 1 || str_starts_with($normalized, '/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'assets/images/')) {
            $normalized = 'images/' . substr($normalized, strlen('assets/images/'));
        } elseif (!str_contains($normalized, '/')) {
            $normalized = 'images/' . $normalized;
        }

        return self::LEGACY_MAIN_IMAGE_MAP[$normalized] ?? $normalized;
    }

    public function getTotalStock(): int
    {
        $total = 0;
        foreach ($this->variants as $variant) {
            $total += $variant->getStock();
        }
        return $total;
    }

    public function getReservedStock(): int
    {
        $reserved = 0;
        foreach ($this->variants as $variant) {
            $reserved += $variant->getReservedStock();
        }
        return $reserved;
    }

    public function getAvailableStock(): int
    {
        $available = 0;
        foreach ($this->variants as $variant) {
            $available += $variant->getAvailableStock();
        }
        return $available;
    }

    public function getLowestPrice(): string
    {
        $lowest = $this->basePrice;
        foreach ($this->variants as $variant) {
            $variantPrice = bcadd($this->basePrice, $variant->getPriceModifier(), 2);
            if (bccomp($variantPrice, $lowest, 2) < 0) {
                $lowest = $variantPrice;
            }
        }
        return $lowest;
    }

    public function getHighestPrice(): string
    {
        $highest = $this->basePrice;
        foreach ($this->variants as $variant) {
            $variantPrice = bcadd($this->basePrice, $variant->getPriceModifier(), 2);
            if (bccomp($variantPrice, $highest, 2) > 0) {
                $highest = $variantPrice;
            }
        }
        return $highest;
    }
}
