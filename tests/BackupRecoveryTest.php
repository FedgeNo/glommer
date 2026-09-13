<?php

declare(strict_types=1);

class BackupRecoveryTest extends TestCase
{
    private function command(array $command): array
    {
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        return [proc_close($process), $output];
    }

    private function removeFixture(string $path): void
    {
        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $child = $path . '/' . $name;
            if (is_dir($child) && !is_link($child)) $this -> removeFixture($child);
            else unlink($child);
        }
        rmdir($path);
    }

    public function testRestorePreviewAcceptsDatabaseOnlyAndSkipsAnUnfinishedNewerRun(): void
    {
        $root = sys_get_temp_dir() . '/glommer-restore-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        mkdir($root . '/2026-01-01_010000', 0700);
        mkdir($root . '/2026-01-02_010000', 0700);
        $dump = $root . '/2026-01-01_010000/database.sql.gz';
        file_put_contents($dump, gzencode('fixture database only'));
        file_put_contents($root . '/2026-01-02_010000/database.partial.sql.gz', 'unfinished');
        $hash = hash_file('sha256', $dump);
        try {
            [$exit, $output] = $this -> command(['env', 'BACKUP_DIR=' . $root, 'GLOMMER_RESTORE_CONFIRMED=0',
                PHP_BINARY, dirname(__DIR__) . '/bin/restore.php']);
            $this -> assertSame(0, $exit);
            $this -> assertTrue(str_contains($output, 'from run:  2026-01-01_010000'));
            $this -> assertTrue(str_contains($output, 'Nothing has been changed.'));
            $this -> assertFalse(str_contains($output, 'uploads.tar.gz'));
            $this -> assertSame($hash, hash_file('sha256', $dump));
            $this -> assertFalse(file_exists($root . '/2026-01-01_010000/database.restore.sql'));
        } finally {
            $this -> removeFixture($root);
        }
    }

    public function testOnlyTheOffServerPrivateKeyCanRecoverTheBundle(): void
    {
        if ($this -> command(['gpg', '--version'])[0] !== 0) {
            throw new TestSkippedException('GnuPG is required for encrypted recovery tests');
        }
        $root = sys_get_temp_dir() . '/glommer-recovery-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        mkdir($root . '/offline', 0700);
        mkdir($root . '/server', 0700);
        $offline = ['gpg', '--homedir', $root . '/offline', '--batch'];
        try {
            $this -> assertSame(0, $this -> command(array_merge($offline, [
                '--pinentry-mode', 'loopback', '--passphrase', '', '--quick-generate-key',
                'Glommer isolated recovery fixture', 'rsa2048', 'encr', '1d',
            ]))[0]);
            $recipient = $root . '/server/recovery-public.asc';
            $this -> assertSame(0, $this -> command(array_merge($offline, ['--armor', '--output', $recipient, '--export']))[0]);
            $plaintext = json_encode(['environmentFile' => 'fixture only', 'effectiveEnvironment' => [
                'ACTIVITYPUB_ENCRYPTION_KEY' => bin2hex(random_bytes(32)),
                'ACTIVITYPUB_ENCRYPTION_KEY_PREVIOUS' => bin2hex(random_bytes(32)),
            ]], JSON_THROW_ON_ERROR);
            $bundle = $root . '/server/' . BackupRecovery::FILENAME;
            BackupRecovery::encrypt($plaintext, $recipient, $bundle);
            $this -> assertSame(0600, fileperms($bundle) & 0777);
            $this -> assertFalse(str_contains(file_get_contents($bundle), 'ACTIVITYPUB_ENCRYPTION_KEY'));
            $this -> assertSame([0, $plaintext], $this -> command(array_merge($offline, ['--decrypt', $bundle])));
            $this -> assertTrue($this -> command(['gpg', '--homedir', $root . '/server', '--batch', '--no-autostart', '--decrypt', $bundle])[0] !== 0);

            // A failed attempt must not overwrite an existing good bundle.
            $hash = hash_file('sha256', $bundle);
            try {
                BackupRecovery::encrypt('replacement', $recipient, $bundle);
                $this -> assertTrue(false, 'Existing backup was overwritten');
            } catch (\RuntimeException $exception) {
                $this -> assertSame($hash, hash_file('sha256', $bundle));
            }
            // Parseable-looking but invalid public material fails cleanly,
            // leaves no partial bundle, and never falls back to plaintext.
            file_put_contents($recipient, "-----BEGIN PGP PUBLIC KEY BLOCK-----\ninvalid\n-----END PGP PUBLIC KEY BLOCK-----\n");
            $bad = $root . '/server/failed.gpg';
            try {
                BackupRecovery::encrypt($plaintext, $recipient, $bad);
                $this -> assertTrue(false, 'Invalid public key was accepted');
            } catch (\RuntimeException $exception) {
                $this -> assertFalse(file_exists($bad));
            }
        } finally {
            $this -> command(['gpgconf', '--homedir', $root . '/offline', '--kill', 'gpg-agent']);
            $this -> removeFixture($root);
        }
    }
}
