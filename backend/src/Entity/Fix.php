<?php
namespace App\Entity;

use App\Repository\FixRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FixRepository::class)]
#[ORM\Table(name: 'fix')]
class Fix
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Vulnerability::class, inversedBy: 'fix')]
    #[ORM\JoinColumn(nullable: false)]
    private Vulnerability $vulnerability;

    #[ORM\ManyToOne(targetEntity: ScanResult::class, inversedBy: 'fixes')]
    #[ORM\JoinColumn(nullable: false)]
    private ScanResult $scanResult;

    #[ORM\Column(type: 'text')]
    private string $originalCode;

    #[ORM\Column(type: 'text')]
    private string $fixedCode;

    #[ORM\Column(type: 'text')]
    private string $explanation;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $appliedAt = null;

    public function __construct() { $this->generatedAt = new \DateTimeImmutable(); }

    public function getId(): ?int { return $this->id; }

    public function getVulnerability(): Vulnerability { return $this->vulnerability; }
    public function setVulnerability(Vulnerability $v): self { $this->vulnerability = $v; return $this; }

    public function getScanResult(): ScanResult { return $this->scanResult; }
    public function setScanResult(ScanResult $s): self { $this->scanResult = $s; return $this; }

    public function getOriginalCode(): string { return $this->originalCode; }
    public function setOriginalCode(string $c): self { $this->originalCode = $c; return $this; }

    public function getFixedCode(): string { return $this->fixedCode; }
    public function setFixedCode(string $c): self { $this->fixedCode = $c; return $this; }

    public function getExplanation(): string { return $this->explanation; }
    public function setExplanation(string $e): self { $this->explanation = $e; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }

    public function getGeneratedAt(): \DateTimeImmutable { return $this->generatedAt; }
    public function getAppliedAt(): ?\DateTimeImmutable { return $this->appliedAt; }
    public function setAppliedAt(?\DateTimeImmutable $d): self { $this->appliedAt = $d; return $this; }
}