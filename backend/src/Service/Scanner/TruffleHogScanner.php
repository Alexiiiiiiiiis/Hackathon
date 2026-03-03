<?php
namespace App\Service\Scanner;

use App\DTO\VulnerabilityDTO;
use App\Enum\OwaspCategory;
use App\Enum\Severity;
use Psr\Log\LoggerInterface;

class TruffleHogScanner implements ScannerInterface
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function getName(): string { return 'trufflehog'; }

    public function isAvailable(): bool
    {
        $cmd = PHP_OS_FAMILY === 'Windows' ? 'where trufflehog 2>nul' : 'which trufflehog 2>/dev/null';
        exec($cmd, $o, $c);
        return $c === 0;
    }

    public function scan(string $projectPath): array
    {
        exec(
            sprintf('trufflehog filesystem --directory=%s --json --no-update 2>/dev/null', escapeshellarg($projectPath)),
            $out
        );

        $results = [];
        foreach ($out as $line) {
            $f = json_decode($line, true);
            if (!$f || json_last_error() !== JSON_ERROR_NONE) continue;
            $name = $f['DetectorName'] ?? 'unknown';
            $meta = $f['SourceMetadata']['Data']['Filesystem'] ?? [];
            $results[] = new VulnerabilityDTO(
                toolSource:    $this->getName(),
                ruleId:        "trufflehog.{$name}",
                message:       "Secret détecté : {$name} — révoquer immédiatement",
                severity:      Severity::CRITICAL,
                filePath:      $meta['file'] ?? null,
                line:          $meta['line'] ?? null,
                codeSnippet:   $f['Raw'] ?? null,
                owaspCategory: OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
            );
        }
        return $results;
    }
}