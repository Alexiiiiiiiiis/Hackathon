<?php
namespace App\Service;

use App\Entity\Project;
use App\Entity\ScanResult;
use App\Service\Scanner\ScannerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ScanOrchestrator
{
    /** @param ScannerInterface[] $scanners */
    public function __construct(
        private readonly iterable               $scanners,
        private readonly OWASPMappingService    $owaspMapper,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
    ) {}

    public function runScan(Project $project): ScanResult
    {
        $scan = new ScanResult();
        $scan->setProject($project)->setStatus('running');
        $this->em->persist($scan);
        $this->em->flush();

        $path = $project->getLocalPath();
        if (!$path || !is_dir($path)) {
            $scan->setStatus('failed');
            $this->em->flush();
            throw new \RuntimeException("Chemin projet introuvable : {$path}");
        }

        foreach ($this->scanners as $scanner) {
            if (!$scanner->isAvailable()) {
                $this->logger->warning("Scanner non disponible : {$scanner->getName()}");
                continue;
            }
            $this->logger->info("Lancement de {$scanner->getName()}");
            try {
                $dtos = $scanner->scan($path);
                $this->logger->info("{$scanner->getName()} : " . count($dtos) . " findings");
                foreach ($dtos as $dto) {
                    $this->owaspMapper->mapAndPersist($dto, $scan);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Erreur {$scanner->getName()}", ['exception' => $e]);
            }
        }

        $scan->computeScore();
        $scan->setStatus('completed');
        $scan->setFinishedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $scan;
    }
}