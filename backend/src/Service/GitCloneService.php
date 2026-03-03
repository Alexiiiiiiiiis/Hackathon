<?php

namespace App\Service;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GitCloneService
{
    private string $clonePath;

    public function __construct(?string $clonePath = null)
    {
        $basePath = $clonePath ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'securescan_repos');
        $this->clonePath = rtrim($basePath, "\\/");
    }

    public function clone(string $gitUrl, int $projectId): string
    {
        if (!is_dir($this->clonePath) && !mkdir($this->clonePath, 0775, true) && !is_dir($this->clonePath)) {
            throw new RuntimeException(sprintf('Impossible de creer le dossier de clonage: %s', $this->clonePath));
        }

        $destination = $this->clonePath . DIRECTORY_SEPARATOR . 'project_' . $projectId;

        if (is_dir($destination)) {
            $this->deleteDirectory($destination);
        }

        $lastProcess = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $process = new Process(['git', 'clone', '--depth', '1', $gitUrl, $destination]);
            $process->setTimeout(120);
            $process->setEnv([
                'GIT_TERMINAL_PROMPT' => '0',
                'HTTP_PROXY' => null,
                'HTTPS_PROXY' => null,
                'ALL_PROXY' => null,
            ]);
            $process->run();
            $lastProcess = $process;

            if ($process->isSuccessful()) {
                return $destination;
            }

            $isThreadIssue = str_contains($process->getErrorOutput(), 'getaddrinfo() thread failed to start');
            if (!$isThreadIssue || $attempt === 3) {
                break;
            }

            usleep(300000);
        }

        if (
            $lastProcess !== null
            && str_contains($lastProcess->getErrorOutput(), 'getaddrinfo() thread failed to start')
            && $this->isGitHubUrl($gitUrl)
        ) {
            $this->cloneFromGithubArchive($gitUrl, $destination);

            return $destination;
        }

        throw new ProcessFailedException($lastProcess);
    }

    public function detectLanguage(string $repoPath): string
    {
        if (file_exists($repoPath . DIRECTORY_SEPARATOR . 'package.json')) return 'javascript';
        if (file_exists($repoPath . DIRECTORY_SEPARATOR . 'composer.json')) return 'php';
        if (file_exists($repoPath . DIRECTORY_SEPARATOR . 'requirements.txt')) return 'python';
        if (file_exists($repoPath . DIRECTORY_SEPARATOR . 'go.mod')) return 'go';

        return 'unknown';
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }

    private function isGitHubUrl(string $url): bool
    {
        return (bool) preg_match('#^https://github\.com/[^/]+/[^/]+(\.git)?$#i', $url);
    }

    private function cloneFromGithubArchive(string $gitUrl, string $destination): void
    {
        $repo = preg_replace('#\.git$#i', '', rtrim($gitUrl, '/'));
        $archiveUrls = [
            $repo . '/archive/refs/heads/main.zip',
            $repo . '/archive/refs/heads/master.zip',
        ];

        $downloadedZip = null;
        foreach ($archiveUrls as $archiveUrl) {
            $zipContent = @file_get_contents($archiveUrl, false, stream_context_create([
                'http' => [
                    'header' => "User-Agent: SecureScan\r\n",
                    'timeout' => 60,
                ],
            ]));

            if ($zipContent === false) {
                continue;
            }

            $tmpZip = tempnam(sys_get_temp_dir(), 'securescan_zip_');
            if ($tmpZip === false) {
                throw new RuntimeException('Impossible de creer le fichier ZIP temporaire.');
            }

            file_put_contents($tmpZip, $zipContent);
            $downloadedZip = $tmpZip;
            break;
        }

        if ($downloadedZip === null) {
            throw new RuntimeException('Le clonage Git a echoue et le fallback archive GitHub n a pas pu etre telecharge.');
        }

        $extractDir = $this->clonePath . DIRECTORY_SEPARATOR . 'extract_' . uniqid('', true);
        if (!mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
            @unlink($downloadedZip);
            throw new RuntimeException(sprintf('Impossible de creer le dossier d extraction: %s', $extractDir));
        }

        $zip = new \ZipArchive();
        $open = $zip->open($downloadedZip);
        if ($open !== true) {
            @unlink($downloadedZip);
            $this->deleteDirectory($extractDir);
            throw new RuntimeException('Impossible d ouvrir l archive GitHub telechargee.');
        }

        $zip->extractTo($extractDir);
        $zip->close();
        @unlink($downloadedZip);

        $entries = array_values(array_filter(scandir($extractDir) ?: [], static fn(string $e): bool => $e !== '.' && $e !== '..'));
        if ($entries === []) {
            $this->deleteDirectory($extractDir);
            throw new RuntimeException('L extraction de l archive GitHub n a retourne aucun fichier.');
        }

        $rootPath = $extractDir . DIRECTORY_SEPARATOR . $entries[0];
        if (!is_dir($rootPath)) {
            $this->deleteDirectory($extractDir);
            throw new RuntimeException('Structure d archive GitHub inattendue.');
        }

        if (is_dir($destination)) {
            $this->deleteDirectory($destination);
        }

        if (!rename($rootPath, $destination)) {
            $this->deleteDirectory($extractDir);
            throw new RuntimeException('Impossible de deplacer le repository extrait vers la destination.');
        }

        $this->deleteDirectory($extractDir);
    }
}
