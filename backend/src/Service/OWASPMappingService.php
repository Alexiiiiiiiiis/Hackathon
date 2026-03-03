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
        'sql' => "Utiliser des requetes preparees (PDO prepare/execute).",
        'xss' => "Echapper les sorties (htmlspecialchars/DOMPurify).",
        'password' => "Hasher avec Argon2 ou bcrypt.",
        'secret' => "Deplacer les secrets dans des variables d environnement.",
        'hardcoded' => "Supprimer les valeurs sensibles en dur.",
        'eval' => "Eviter eval et parser les donnees de facon sure.",
        'csrf' => "Ajouter un token CSRF sur les formulaires.",
        'cors' => "Restreindre CORS a une liste blanche explicite.",
        'jwt' => "Verifier signature, expiration et claims.",
        'serial' => "Eviter la deserialisation de donnees non fiables.",
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
        $text = strtolower($message . ' ' . $ruleId);
        $map = [
            OwaspCategory::A05_INJECTION => ['sql', 'xss', 'inject', 'eval', 'command', 'ldap'],
            OwaspCategory::A04_CRYPTOGRAPHIC_FAILURES => ['password', 'secret', 'crypto', 'md5', 'sha1', 'hardcoded'],
            OwaspCategory::A01_BROKEN_ACCESS_CONTROL => ['idor', 'cors', 'access', 'privilege'],
            OwaspCategory::A02_SECURITY_MISCONFIGURATION => ['header', 'debug', 'config', 'default'],
            OwaspCategory::A03_SUPPLY_CHAIN_FAILURES => ['dependenc', 'package', 'cve', 'outdated'],
            OwaspCategory::A07_AUTHENTICATION_FAILURES => ['auth', 'session', 'jwt', 'token', 'csrf'],
            OwaspCategory::A09_LOGGING_FAILURES => ['log', 'monitor', 'alert'],
            OwaspCategory::A10_EXCEPTIONAL_CONDITIONS => ['exception', 'error', 'stack trace', 'unhandled'],
            OwaspCategory::A08_INTEGRITY_FAILURES => ['serial', 'deseri', 'integrity'],
            OwaspCategory::A06_INSECURE_DESIGN => ['validation', 'insecure', 'unsafe'],
        ];

        foreach ($map as $cat => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    return $cat;
                }
            }
        }

        return OwaspCategory::UNKNOWN;
    }

    private function buildFix(VulnerabilityDTO $dto): ?string
    {
        $text = strtolower($dto->ruleId . ' ' . $dto->message);
        foreach (self::FIXES as $kw => $fix) {
            if (str_contains($text, $kw)) {
                return $fix;
            }
        }

        return null;
    }

    public function buildStats(ScanResult $scanResult): array
    {
        $vulns = $scanResult->getVulnerabilities()->toArray();
        $bySeverity = array_fill_keys(array_column(Severity::cases(), 'value'), 0);
        $byOwasp = [];

        foreach ($vulns as $v) {
            $bySeverity[$v->getSeverity()->value]++;
            $key = $v->getOwaspCategory()->value;
            $byOwasp[$key] = ($byOwasp[$key] ?? 0) + 1;
        }

        return [
            'totalVulns' => count($vulns),
            'bySeverity' => $bySeverity,
            'byOwasp' => $byOwasp,
            'coveredCategories' => count($byOwasp),
            'score' => $scanResult->getGlobalScore() ?? 0,
            'grade' => $scanResult->getGrade() ?? 'N/A',
        ];
    }
}

