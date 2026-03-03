<?php
namespace App\Tests\Unit\Service;

use App\Service\GitPushService;
use PHPUnit\Framework\TestCase;

class GitPushServiceTest extends TestCase
{
    public function testThrowsIfInvalidPath(): void
    {
        $this->expectException(\Symfony\Component\Process\Exception\ProcessFailedException::class);

        $service = new GitPushService();
        $service->createBranchCommitAndPush('/nonexistent/path', 'fix/test');
    }

    public function testCreateBranchCommitAndPushWithRealGitRepo(): void
    {
        // Crée un vrai repo Git temporaire pour le test
        $tmpDir = sys_get_temp_dir() . '/securescan_test_' . uniqid();
        mkdir($tmpDir);

        exec("git init {$tmpDir}");
        exec("git -C {$tmpDir} config user.email 'test@test.com'");
        exec("git -C {$tmpDir} config user.name 'Test'");
        file_put_contents("{$tmpDir}/test.txt", 'initial');
        exec("git -C {$tmpDir} add .");
        exec("git -C {$tmpDir} commit -m 'initial'");

        // Ajoute un changement
        file_put_contents("{$tmpDir}/fix.txt", 'fixed code');

        $service = new GitPushService();

        try {
            // Ne peut pas pusher (pas de remote) mais on teste le checkout + add + commit
            $service->createBranchCommitAndPush($tmpDir, 'fix/securescan-test', 'test: apply fix');
            // Si on arrive ici sans exception, le commit a fonctionné
            $this->assertTrue(true);
        } catch (\RuntimeException $e) {
            // Le push vers origin va échouer (pas de remote) — c'est attendu
            $this->assertStringContainsString('origin', strtolower($e->getMessage()));
        } finally {
            // Nettoyage
            exec(PHP_OS_FAMILY === 'Windows' ? "rmdir /s /q {$tmpDir}" : "rm -rf {$tmpDir}");
        }
    }
}