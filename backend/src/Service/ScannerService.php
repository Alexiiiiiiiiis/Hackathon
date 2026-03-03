<?php
namespace App\Service;

use App\Entity\Project;
use App\Entity\ScanResult;

class ScannerService
{
    public function __construct(private readonly ScanOrchestrator $orchestrator) {}

    public function launch(Project $project): ScanResult
    {
        return $this->orchestrator->runScan($project);
    }
}
