<?php

namespace App\Entity;

use App\Repository\AgeVerificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgeVerificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AgeVerification
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending Review',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
    ];

    public const ID_TYPE_DRIVERS_LICENSE = 'drivers_license';
    public const ID_TYPE_PASSPORT = 'passport';
    public const ID_TYPE_NATIONAL_ID = 'national_id';
    public const ID_TYPE_POSTAL_ID = 'postal_id';
    public const ID_TYPE_VOTERS_ID = 'voters_id';
    public const ID_TYPE_SSS = 'sss';
    public const ID_TYPE_PHILHEALTH = 'philhealth';

    public const ID_TYPES = [
        self::ID_TYPE_DRIVERS_LICENSE => "Driver's License",
        self::ID_TYPE_PASSPORT => 'Passport',
        self::ID_TYPE_NATIONAL_ID => 'Philippine National ID',
        self::ID_TYPE_POSTAL_ID => 'Postal ID',
        self::ID_TYPE_VOTERS_ID => "Voter's ID",
        self::ID_TYPE_SSS => 'SSS ID',
        self::ID_TYPE_PHILHEALTH => 'PhilHealth ID',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private ?string $idType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $idNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $idFrontImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $idBackImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $selfieImage = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateOfBirth = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reviewedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $reviewedBy = null;

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

    public function getIdType(): ?string
    {
        return $this->idType;
    }

    public function setIdType(string $idType): static
    {
        $this->idType = $idType;
        return $this;
    }

    public function getIdTypeLabel(): string
    {
        return self::ID_TYPES[$this->idType] ?? $this->idType;
    }

    public function getIdNumber(): ?string
    {
        return $this->idNumber;
    }

    public function setIdNumber(?string $idNumber): static
    {
        $this->idNumber = $idNumber;
        return $this;
    }

    public function getIdFrontImage(): ?string
    {
        return $this->idFrontImage;
    }

    public function setIdFrontImage(string $idFrontImage): static
    {
        $this->idFrontImage = $idFrontImage;
        return $this;
    }

    public function getIdBackImage(): ?string
    {
        return $this->idBackImage;
    }

    public function setIdBackImage(?string $idBackImage): static
    {
        $this->idBackImage = $idBackImage;
        return $this;
    }

    public function getSelfieImage(): ?string
    {
        return $this->selfieImage;
    }

    public function setSelfieImage(?string $selfieImage): static
    {
        $this->selfieImage = $selfieImage;
        return $this;
    }

    public function getDateOfBirth(): ?\DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(\DateTimeInterface $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;
        return $this;
    }

    public function getAge(): int
    {
        return $this->dateOfBirth->diff(new \DateTime())->y;
    }

    public function isOfLegalAge(): bool
    {
        return $this->getAge() >= 18;
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

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeInterface
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeInterface $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    public function approve(User $reviewer): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->reviewedBy = $reviewer;
        $this->reviewedAt = new \DateTime();
        $this->rejectionReason = null;

        // Update user's age verification status
        $this->user->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
        $this->user->setAgeVerifiedAt(new \DateTime());
        $this->user->setBirthDate($this->dateOfBirth);
    }

    public function reject(User $reviewer, string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->reviewedBy = $reviewer;
        $this->reviewedAt = new \DateTime();
        $this->rejectionReason = $reason;

        // Update user's age verification status
        $this->user->setAgeVerificationStatus(User::AGE_STATUS_REJECTED);
    }
}
