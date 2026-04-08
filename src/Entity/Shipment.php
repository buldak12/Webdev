<?php

namespace App\Entity;

use App\Repository\ShipmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Shipment
{
    // Common PH couriers
    public const COURIER_LBC = 'lbc';
    public const COURIER_JT = 'jt';
    public const COURIER_NINJA_VAN = 'ninja_van';
    public const COURIER_GRAB_EXPRESS = 'grab_express';
    public const COURIER_LALAMOVE = 'lalamove';
    public const COURIER_FLASH_EXPRESS = 'flash_express';
    public const COURIER_ENTREGO = 'entrego';

    public const COURIERS = [
        self::COURIER_LBC => 'LBC Express',
        self::COURIER_JT => 'J&T Express',
        self::COURIER_NINJA_VAN => 'Ninja Van',
        self::COURIER_GRAB_EXPRESS => 'Grab Express',
        self::COURIER_LALAMOVE => 'Lalamove',
        self::COURIER_FLASH_EXPRESS => 'Flash Express',
        self::COURIER_ENTREGO => 'Entrego',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_PACKED = 'packed';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETURNED = 'returned';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PREPARING => 'Preparing',
        self::STATUS_PACKED => 'Packed',
        self::STATUS_PICKED_UP => 'Picked Up by Courier',
        self::STATUS_IN_TRANSIT => 'In Transit',
        self::STATUS_OUT_FOR_DELIVERY => 'Out for Delivery',
        self::STATUS_DELIVERED => 'Delivered',
        self::STATUS_FAILED => 'Delivery Failed',
        self::STATUS_RETURNED => 'Returned',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $courier = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $shippingCost = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $weight = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $trackingHistory = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $packedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $shippedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deliveredAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $estimatedDelivery = null;

    #[ORM\OneToOne(inversedBy: 'shipment', targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $packedBy = null;

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

    public function getCourier(): ?string
    {
        return $this->courier;
    }

    public function setCourier(?string $courier): static
    {
        $this->courier = $courier;
        return $this;
    }

    public function getCourierLabel(): string
    {
        return self::COURIERS[$this->courier] ?? $this->courier ?? '';
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $oldStatus = $this->status;
        $this->status = $status;
        
        // Add to tracking history
        if ($oldStatus !== $status) {
            $this->addTrackingEvent($status, self::STATUSES[$status] ?? $status);
        }
        
        if ($status === self::STATUS_PACKED && !$this->packedAt) {
            $this->packedAt = new \DateTime();
        }
        if (in_array($status, [self::STATUS_PICKED_UP, self::STATUS_IN_TRANSIT]) && !$this->shippedAt) {
            $this->shippedAt = new \DateTime();
        }
        if ($status === self::STATUS_DELIVERED && !$this->deliveredAt) {
            $this->deliveredAt = new \DateTime();
        }
        
        return $this;
    }

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getShippingCost(): string
    {
        return $this->shippingCost;
    }

    public function setShippingCost(string $shippingCost): static
    {
        $this->shippingCost = $shippingCost;
        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getTrackingHistory(): array
    {
        return $this->trackingHistory ?? [];
    }

    public function addTrackingEvent(string $status, string $description, ?string $location = null): static
    {
        $event = [
            'status' => $status,
            'description' => $description,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
        
        if ($location) {
            $event['location'] = $location;
        }
        
        $this->trackingHistory[] = $event;
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

    public function getPackedAt(): ?\DateTimeInterface
    {
        return $this->packedAt;
    }

    public function setPackedAt(?\DateTimeInterface $packedAt): static
    {
        $this->packedAt = $packedAt;
        return $this;
    }

    public function getShippedAt(): ?\DateTimeInterface
    {
        return $this->shippedAt;
    }

    public function setShippedAt(?\DateTimeInterface $shippedAt): static
    {
        $this->shippedAt = $shippedAt;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeInterface
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeInterface $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getEstimatedDelivery(): ?\DateTimeInterface
    {
        return $this->estimatedDelivery;
    }

    public function setEstimatedDelivery(?\DateTimeInterface $estimatedDelivery): static
    {
        $this->estimatedDelivery = $estimatedDelivery;
        return $this;
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

    public function getPackedBy(): ?User
    {
        return $this->packedBy;
    }

    public function setPackedBy(?User $packedBy): static
    {
        $this->packedBy = $packedBy;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPacked(): bool
    {
        return $this->status === self::STATUS_PACKED;
    }

    public function isShipped(): bool
    {
        return in_array($this->status, [
            self::STATUS_PICKED_UP,
            self::STATUS_IN_TRANSIT,
            self::STATUS_OUT_FOR_DELIVERY,
        ]);
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function canBeShipped(): bool
    {
        return $this->status === self::STATUS_PACKED && $this->trackingNumber !== null;
    }

    public function markAsPacked(User $packedBy): void
    {
        $this->status = self::STATUS_PACKED;
        $this->packedAt = new \DateTime();
        $this->packedBy = $packedBy;
        $this->addTrackingEvent(self::STATUS_PACKED, 'Order has been packed and is ready for pickup');
    }

    public function markAsShipped(string $courier, string $trackingNumber): void
    {
        $this->courier = $courier;
        $this->trackingNumber = $trackingNumber;
        $this->status = self::STATUS_PICKED_UP;
        $this->shippedAt = new \DateTime();
        $this->addTrackingEvent(self::STATUS_PICKED_UP, 'Package picked up by ' . (self::COURIERS[$courier] ?? $courier));
    }

    public function markAsDelivered(): void
    {
        $this->status = self::STATUS_DELIVERED;
        $this->deliveredAt = new \DateTime();
        $this->addTrackingEvent(self::STATUS_DELIVERED, 'Package has been delivered');
    }
}
