<?php
namespace App\Service;

use RuntimeException;
use Symfony\Component\Process\Process;

class GitPushService
{
    public function createBranchCommitAndPush(
        string $repoPath,
        string $branchName,
        string $commitMessage = 'chore: apply securescan fixes'
    ): void {
        $this->run(['git', 'checkout', '-b', $branchName], $repoPath);
        $this->run(['git', 'add', '-A'], $repoPath);

        $status = new Process(['git', 'status', '--porcelain'], $repoPath);
        $status->run();
        if (!$status->isSuccessful()) {
            throw new RuntimeException('Impossible de verifier le statut git.');
        }
        if (trim($status->getOutput()) === '') {
            return;
        }

        $this->run(['git', 'commit', '-m', $commitMessage], $repoPath);
        $this->run(['git', 'push', '-u', 'origin', $branchName], $repoPath);
    }

    private function run(array $command, string $cwd): void
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Commande git en echec.');
        }
    }
}
