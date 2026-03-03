<?php

namespace App\Entity;

use App\Repository\FixRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FixRepository::class)]
class Fix
{
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REJECTED = 'rejected';

    private const ALLOWED_STATUSES = [
        self::STATUS_PROPOSED,
        self::STATUS_APPLIED,
        self::STATUS_REJECTED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vulnerability $vulnerability = null;

    #[ORM\Column(type: 'text')]
    private ?string $originalCode = null;

    #[ORM\Column(type: 'text')]
    private ?string $fixedCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $explanation = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PROPOSED;

    public function getId(): ?int { return $this->id; }
    public function getVulnerability(): ?Vulnerability { return $this->vulnerability; }
    public function setVulnerability(Vulnerability $vulnerability): static { $this->vulnerability = $vulnerability; return $this; }
    public function getOriginalCode(): ?string { return $this->originalCode; }
    public function setOriginalCode(string $originalCode): static { $this->originalCode = $originalCode; return $this; }
    public function getFixedCode(): ?string { return $this->fixedCode; }
    public function setFixedCode(string $fixedCode): static { $this->fixedCode = $fixedCode; return $this; }
    public function getExplanation(): ?string { return $this->explanation; }
    public function setExplanation(?string $explanation): static { $this->explanation = $explanation; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid fix status: %s', $status));
        }

        $this->status = $status;

        return $this;
    }
}
