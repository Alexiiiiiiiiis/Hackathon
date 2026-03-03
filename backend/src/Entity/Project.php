<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLONING = 'cloning';
    public const STATUS_SCANNING = 'scanning';
    public const STATUS_SCANNED = 'scanned';
    public const STATUS_ERROR = 'error';

    private const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CLONING,
        self::STATUS_SCANNING,
        self::STATUS_SCANNED,
        self::STATUS_ERROR,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500)]
    private ?string $gitUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Vulnerability::class, cascade: ['remove'])]
    private Collection $vulnerabilities;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ScanResult::class, cascade: ['remove'])]
    private Collection $scanResults;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->vulnerabilities = new ArrayCollection();
        $this->scanResults = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getGitUrl(): ?string { return $this->gitUrl; }
    public function setGitUrl(string $gitUrl): static { $this->gitUrl = $gitUrl; return $this; }
    public function getLanguage(): ?string { return $this->language; }
    public function setLanguage(?string $language): static { $this->language = $language; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid project status: %s', $status));
        }

        $this->status = $status;

        return $this;
    }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getVulnerabilities(): Collection { return $this->vulnerabilities; }
    public function getScanResults(): Collection { return $this->scanResults; }
}
