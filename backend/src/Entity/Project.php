<?php
namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 512)]
    private string $source;

    #[ORM\Column(length: 20)]
    private string $sourceType;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $localPath = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $detectedLanguage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $owner = null;

    #[ORM\OneToMany(targetEntity: ScanResult::class, mappedBy: 'project', cascade: ['persist', 'remove'])]
    private Collection $scanResults;

    public function __construct()
    {
        $this->createdAt   = new \DateTimeImmutable();
        $this->scanResults = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name = $n; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $s): self { $this->source = $s; return $this; }

    public function getSourceType(): string { return $this->sourceType; }
    public function setSourceType(string $t): self { $this->sourceType = $t; return $this; }

    public function getLocalPath(): ?string { return $this->localPath; }
    public function setLocalPath(?string $p): self { $this->localPath = $p; return $this; }

    public function getDetectedLanguage(): ?string { return $this->detectedLanguage; }
    public function setDetectedLanguage(?string $l): self { $this->detectedLanguage = $l; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $u): self { $this->owner = $u; return $this; }

    public function getScanResults(): Collection { return $this->scanResults; }
}