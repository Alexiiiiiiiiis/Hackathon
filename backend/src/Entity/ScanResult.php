<?php

namespace App\Entity;

use App\Repository\ScanResultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScanResultRepository::class)]
class ScanResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'scanResults')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\Column(length: 100)]
    private ?string $tool = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rawJson = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $project): static { $this->project = $project; return $this; }
    public function getTool(): ?string { return $this->tool; }
    public function setTool(string $tool): static { $this->tool = $tool; return $this; }
    public function getRawJson(): ?array { return $this->rawJson; }
    public function setRawJson(?array $rawJson): static { $this->rawJson = $rawJson; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}