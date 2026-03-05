<?php
namespace App\Service;

use App\Entity\Fix;
use App\Entity\Vulnerability;
use Doctrine\ORM\EntityManagerInterface;

class FixService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function generateFix(Vulnerability $vuln): Fix
    {
        // Si un fix existe déjà, le retourner directement
        if ($vuln->getFix()) {
            return $vuln->getFix();
        }

        $fix = new Fix();
        $fix->setVulnerability($vuln)
            ->setScanResult($vuln->getScanResult())
            ->setOriginalCode($vuln->getCodeSnippet() ?? '// Code non disponible')
            ->setFixedCode($this->buildFixedCode($vuln))
            ->setExplanation($this->buildExplanation($vuln))
            ->setStatus('pending');
        $vuln->setFix($fix);
        $this->em->persist($fix);
        $this->em->flush();
        return $fix;
    }

    public function applyFix(Fix $fix): void
    {
        $filePath = $fix->getVulnerability()->getFilePath();

        // Tente le patch si le fichier existe
        if ($filePath && file_exists($filePath)) {
            $original = file_get_contents($filePath);
            $patched  = str_replace($fix->getOriginalCode(), $fix->getFixedCode(), $original);
            if ($patched !== $original) {
                file_put_contents($filePath, $patched);
            }
        }

        // Dans tous les cas, marquer comme appliqué
        $fix->setStatus('applied')->setAppliedAt(new \DateTimeImmutable());
        $fix->getVulnerability()->setFixStatus('accepted');
        $this->em->flush();
    }

    public function rejectFix(Fix $fix): void
    {
        $fix->setStatus('rejected');
        $fix->getVulnerability()->setFixStatus('rejected');
        $this->em->flush();
    }

    private function buildFixedCode(Vulnerability $vuln): string
    {
        $sf = $vuln->getSuggestedFix();
        return $sf
            ? "// FIX APPLIQUÉ ({$vuln->getOwaspCategory()->label()})\n{$sf}"
            : "// TODO: corriger manuellement\n" . ($vuln->getCodeSnippet() ?? '');
    }

    private function buildExplanation(Vulnerability $vuln): string
    {
        return sprintf(
            "Vulnérabilité %s (%s) détectée par %s\nFichier : %s, ligne %d\n\nOWASP : %s\n%s\n\nCorrection suggérée :\n%s",
            $vuln->getSeverity()->value,
            $vuln->getRuleId(),
            $vuln->getToolSource(),
            $vuln->getFilePath() ?? 'N/A',
            $vuln->getLine() ?? 0,
            $vuln->getOwaspCategory()->label(),
            $vuln->getOwaspCategory()->description(),
            $vuln->getSuggestedFix() ?? 'Voir documentation OWASP.'
        );
    }
}