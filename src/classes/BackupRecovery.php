<?php

declare(strict_types=1);

/** Encrypt recovery configuration without writing plaintext to a backup file. */
class BackupRecovery
{
    public const FILENAME = 'recovery.json.gpg';

    public static function recipientFile(): string
    {
        return Env::get('BACKUP_RECIPIENT_FILE', '') ?: Backup::rootDir() . '/recovery-public.asc';
    }

    public static function configuration(): string
    {
        $environment = @file_get_contents(Env::path(), false, null, 0, 1048577);
        if ($environment === false || strlen($environment) > 1048576) {
            throw new \RuntimeException('Cannot read the recovery configuration.');
        }

        // Capture effective values as well as the file: service environment
        // overrides win over .env, including retained encryption keys.
        preg_match_all('/^\s*([A-Z][A-Z0-9_]*)\s*=/m', $environment, $matches);
        $keys = array_unique(array_merge($matches[1], [
            'ACTIVITYPUB_ENCRYPTION_KEY', 'ACTIVITYPUB_ENCRYPTION_KEY_PREVIOUS',
            'WS_SECRET', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        ]));
        $effective = [];
        foreach ($keys as $key) {
            $value = Env::get($key);
            if ($value !== null) $effective[$key] = $value;
        }
        if (($effective['ACTIVITYPUB_ENCRYPTION_KEY'] ?? '') === '') {
            throw new \RuntimeException('The ActivityPub encryption key is missing; recovery would be incomplete.');
        }

        return json_encode([
            'format' => 1,
            'createdAt' => gmdate('c'),
            'environmentFile' => $environment,
            'effectiveEnvironment' => $effective,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
    }

    public static function encrypt(string $plaintext, string $recipient, string $destination): void
    {
        $public_key = @file_get_contents($recipient, false, null, 0, 65537);
        if ($public_key === false || strlen($public_key) > 65536
            || !str_starts_with(trim($public_key), '-----BEGIN PGP PUBLIC KEY BLOCK-----')
            || str_contains($public_key, 'PRIVATE KEY')) {
            throw new \RuntimeException('Configure an armored public recovery key in BACKUP_RECIPIENT_FILE (see README, Backups).');
        }

        $scratch = sys_get_temp_dir() . '/glommer-backup-gpg-' . bin2hex(random_bytes(12));
        if (!mkdir($scratch, 0700)) {
            throw new \RuntimeException('Cannot create the encryption working directory.');
        }
        $output = null;
        $process = null;
        $pipes = [];
        $complete = false;
        try {
            $output = @fopen($destination, 'xb');
            if ($output === false) {
                throw new \RuntimeException('Cannot create the encrypted recovery bundle.');
            }
            if (!chmod($destination, 0600)) {
                throw new \RuntimeException('Cannot protect the encrypted recovery bundle.');
            }
            // Pin the validated public bytes for this operation; do not let
            // a replaced recipient file change the destination key mid-run.
            file_put_contents($scratch . '/recipient.asc', $public_key);
            $process = proc_open([
                'timeout', '--kill-after=5s', '30s', 'gpg', '--no-options',
                '--batch', '--no-tty', '--no-autostart', '--no-keyring',
                '--no-auto-key-retrieve', '--auto-key-locate', 'clear',
                '--homedir', $scratch, '--recipient-file', $scratch . '/recipient.asc',
                '--encrypt', '--output', '-',
            ], [0 => ['pipe', 'r'], 1 => $output, 2 => ['file', '/dev/null', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new \RuntimeException('Cannot start GnuPG for the recovery bundle.');
            }
            $offset = 0;
            while ($offset < strlen($plaintext)) {
                $written = @fwrite($pipes[0], substr($plaintext, $offset));
                if ($written === false || $written === 0) break;
                $offset += $written;
            }
            fclose($pipes[0]);
            $pipes = [];
            $exit = proc_close($process);
            $process = null;
            fflush($output);
            $complete = $offset === strlen($plaintext) && $exit === 0 && fstat($output)['size'] > 0;
            if (!$complete) {
                throw new \RuntimeException('Recovery encryption failed; check GnuPG and the public recovery key.');
            }
        } finally {
            foreach ($pipes as $pipe) fclose($pipe);
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            if (is_resource($output)) {
                fclose($output);
                if (!$complete) @unlink($destination);
            }
            foreach (glob($scratch . '/*') ?: [] as $file) {
                if (is_file($file)) unlink($file);
            }
            rmdir($scratch);
        }
    }
}
