<?php
namespace App\Service\Scanner;

use App\DTO\VulnerabilityDTO;

interface ScannerInterface
{
    public function getName(): string;

    /** @return VulnerabilityDTO[] */
    public function scan(string $projectPath): array;

    public function isAvailable(): bool;
}