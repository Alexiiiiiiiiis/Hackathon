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

        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];
        foreach ($vulns as $v) {
            $key = strtolower($v->getSeverity()->value);
            if (isset($counts[$key])) $counts[$key]++;
        }

        // Pénalité plafonnée par catégorie pour éviter un score systématiquement à 0
        $penalty = min(60, $counts['critical'] * 8)
                 + min(25, $counts['high']     * 3)
                 + min(10, $counts['medium']   * 1)
                 + min(5,  $counts['low']      * 0);

        $this->globalScore = max(1, 100 - (int) $penalty);
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