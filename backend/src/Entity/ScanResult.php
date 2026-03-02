<?php
namespace App\Entity;

use App\Repository\ScanResultRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScanResultRepository::class)]
#[ORM\Table(name: 'scan_result')]
class ScanResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'scanResults')]
    #[ORM\JoinColumn(nullable: false)]
    private Project $project;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(nullable: true)]
    private ?int $globalScore = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $grade = null;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\OneToMany(targetEntity: Vulnerability::class, mappedBy: 'scanResult', cascade: ['persist', 'remove'])]
    private Collection $vulnerabilities;

    #[ORM\OneToMany(targetEntity: Fix::class, mappedBy: 'scanResult', cascade: ['persist', 'remove'])]
    private Collection $fixes;

    public function __construct()
    {
        $this->startedAt       = new \DateTimeImmutable();
        $this->vulnerabilities = new ArrayCollection();
        $this->fixes           = new ArrayCollection();
    }

    public function addVulnerability(Vulnerability $v): self
    {
        if (!$this->vulnerabilities->contains($v)) {
            $this->vulnerabilities->add($v);
            $v->setScanResult($this);
        }
        return $this;
    }

    public function computeScore(): void
    {
        $vulns = $this->vulnerabilities->toArray();
        if (empty($vulns)) {
            $this->globalScore = 100;
            $this->grade = 'A';
            return;
        }
        $penalty = 0;
        foreach ($vulns as $v) {
            $penalty += $v->getSeverity()->penaltyPoints();
        }
        $this->globalScore = max(0, 100 - $penalty);
        $this->grade = match(true) {
            $this->globalScore >= 90 => 'A',
            $this->globalScore >= 75 => 'B',
            $this->globalScore >= 60 => 'C',
            $this->globalScore >= 40 => 'D',
            default                  => 'F',
        };
    }

    public function getId(): ?int { return $this->id; }

    public function getProject(): Project { return $this->project; }
    public function setProject(Project $p): self { $this->project = $p; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }

    public function getGlobalScore(): ?int { return $this->globalScore; }
    public function getGrade(): ?string { return $this->grade; }

    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getFinishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function setFinishedAt(\DateTimeImmutable $d): self { $this->finishedAt = $d; return $this; }

    public function getVulnerabilities(): Collection { return $this->vulnerabilities; }
    public function getFixes(): Collection { return $this->fixes; }
}