<?php
namespace App\Service;

use App\DTO\VulnerabilityDTO;
use App\Entity\ScanResult;
use App\Entity\Vulnerability;
use App\Enum\OwaspCategory;
use App\Enum\Severity;

class OWASPMappingService
{
    private const FIXES = [
        'sql'       => "Requêtes préparées :\n\$stmt = \$pdo->prepare('SELECT * FROM t WHERE id = :id');\n\$stmt->execute([':id' => \$id]);",
        'xss'       => "Échappez les sorties : htmlspecialchars(\$input, ENT_QUOTES, 'UTF-8')\n// JS : DOMPurify.sanitize(userInput)",
        'password'  => "Hachez avec argon2 :\n\$hash = password_hash(\$password, PASSWORD_ARGON2ID);\npassword_verify(\$password, \$hash);",
        'secret'    => "Déplacez vers .env :\nAPI_KEY=your_secret\n// PHP : \$_ENV['API_KEY']",
        'hardcoded' => "Remplacez les valeurs en dur par des variables d'environnement.",
        'eval'      => "Évitez eval(). Utilisez JSON.parse() ou des alternatives sécurisées.",
        'csrf'      => "Ajoutez un token CSRF à chaque formulaire POST.",
        'cors'      => "Configurez CORS avec une liste blanche explicite d'origines.",
        'jwt'       => "Validez la signature, l'expiration et les claims du JWT.",
        'serial'    => "Évitez la désérialisation de données non fiables.",
    ];

    public function mapAndPersist(VulnerabilityDTO $dto, ScanResult $scanResult): Vulnerability
    {
        $vuln = new Vulnerability();
        $vuln->setToolSource($dto->toolSource)
             ->setRuleId($dto->ruleId)
             ->setMessage($dto->message)
             ->setSeverity($dto->severity)
             ->setFilePath($dto->filePath)
             ->setLine($dto->line)
             ->setCodeSnippet($dto->codeSnippet)
             ->setOwaspCategory(
                 $dto->owaspCategory !== OwaspCategory::UNKNOWN
                     ? $dto->owaspCategory
                     : $this->inferOwasp($dto->message, $dto->ruleId)
             )
             ->setSuggestedFix($this->buildFix($dto));
        $scanResult->addVulnerability($vuln);
        return $vuln;
    }

    private function inferOwasp(string $message, string $ruleId): OwaspCategory
    {
        $text = strtolower("{$message} {$ruleId}");
        $map = [
            OwaspCategory::A05_INJECTION                 => ['sql', 'xss', 'inject', 'eval', 'command', 'ldap'],
            OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES    => ['password', 'secret', 'crypto', 'md5', 'sha1', 'hardcoded'],
            OwaspCategory::A01_BROKEN_ACCESS_CONTROL     => ['idor', 'cors', 'access', 'privilege'],
            OwaspCategory::A02_SECURITY_MISCONFIGURATION => ['header', 'debug', 'config', 'default'],
            OwaspCategory::A03_SUPPLY_CHAIN_FAILURES     => ['dependenc', 'package', 'cve', 'outdated'],
            OwaspCategory::A07_AUTHENTICATION_FAILURES   => ['auth', 'session', 'jwt', 'token', 'csrf'],
            OwaspCategory::A09_LOGGING_FAILURES          => ['log', 'monitor', 'alert'],
            OwaspCategory::A10_EXCEPTIONAL_CONDITIONS    => ['exception', 'error', 'stack trace', 'unhandled'],
            OwaspCategory::A08_INTEGRITY_FAILURES        => ['serial', 'deseri', 'integrity'],
            OwaspCategory::A06_INSECURE_DESIGN           => ['validation', 'insecure', 'unsafe'],
        ];
        foreach ($map as $cat => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) return $cat;
            }
        }
        return OwaspCategory::UNKNOWN;
    }

    private function buildFix(VulnerabilityDTO $dto): ?string
    {
        $text = strtolower("{$dto->ruleId} {$dto->message}");
        foreach (self::FIXES as $kw => $fix) {
            if (str_contains($text, $kw)) return $fix;
        }
        return null;
    }

    public function buildStats(ScanResult $scanResult): array
    {
        $vulns      = $scanResult->getVulnerabilities()->toArray();
        $bySeverity = array_fill_keys(array_column(Severity::cases(), 'value'), 0);
        $byOwasp    = [];
        foreach ($vulns as $v) {
            $bySeverity[$v->getSeverity()->value]++;
            $key = $v->getOwaspCategory()->value;
            $byOwasp[$key] = ($byOwasp[$key] ?? 0) + 1;
        }
        return [
            'totalVulns'        => count($vulns),
            'bySeverity'        => $bySeverity,
            'byOwasp'           => $byOwasp,
            'coveredCategories' => count($byOwasp),
            'score'             => $scanResult->getGlobalScore() ?? 0,
            'grade'             => $scanResult->getGrade() ?? 'N/A',
        ];
    }
}