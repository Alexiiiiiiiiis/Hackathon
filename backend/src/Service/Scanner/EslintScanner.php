<?php
namespace App\Service\Scanner;

use App\DTO\VulnerabilityDTO;
use App\Enum\OwaspCategory;
use App\Enum\Severity;
use Psr\Log\LoggerInterface;

class EslintScanner implements ScannerInterface
{
    private const RULE_OWASP_MAP = [
        'detect-eval-with-expression'    => OwaspCategory::A05_INJECTION,
        'detect-child-process'           => OwaspCategory::A05_INJECTION,
        'detect-non-literal-regexp'      => OwaspCategory::A05_INJECTION,
        'detect-new-buffer'              => OwaspCategory::A05_INJECTION,
        'detect-disable-mustache-escape' => OwaspCategory::A05_INJECTION,
        'detect-object-injection'        => OwaspCategory::A01_BROKEN_ACCESS_CONTROL,
        'detect-possible-timing-attacks' => OwaspCategory::A07_AUTHENTICATION_FAILURES,
        'detect-pseudoRandomBytes'       => OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES,
        'detect-unsafe-regex'            => OwaspCategory::A06_INSECURE_DESIGN,
        'no-console'                     => OwaspCategory::A09_LOGGING_FAILURES,
    ];

    public function __construct(private readonly LoggerInterface $logger) {}

    public function getName(): string { return 'eslint'; }

    public function isAvailable(): bool
    {
        exec('which eslint 2>/dev/null', $o, $c);
        return $c === 0;
    }

    public function scan(string $projectPath): array
    {
        if (!$this->hasJsFiles($projectPath)) return [];

        exec(
            sprintf('eslint %s --ext .js,.jsx,.ts,.tsx --plugin security --format json 2>/dev/null', escapeshellarg($projectPath)),
            $out
        );

        $json = json_decode(implode("\n", $out), true);
        if (!is_array($json)) return [];

        $results = [];
        foreach ($json as $fileResult) {
            foreach ($fileResult['messages'] ?? [] as $msg) {
                $ruleId = $msg['ruleId'] ?? 'unknown';
                if (!str_starts_with($ruleId, 'security/')) continue;
                $short = str_replace('security/', '', $ruleId);
                $results[] = new VulnerabilityDTO(
                    toolSource:    $this->getName(),
                    ruleId:        $ruleId,
                    message:       $msg['message'] ?? 'ESLint security warning',
                    severity:      $msg['severity'] === 2 ? Severity::HIGH : Severity::MEDIUM,
                    filePath:      $fileResult['filePath'] ?? null,
                    line:          $msg['line'] ?? null,
                    owaspCategory: self::RULE_OWASP_MAP[$short] ?? OwaspCategory::UNKNOWN,
                );
            }
        }
        return $results;
    }

    private function hasJsFiles(string $path): bool
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if (in_array($f->getExtension(), ['js', 'jsx', 'ts', 'tsx'])) return true;
        }
        return false;
    }
}