<?php

declare(strict_types=1);

/** Installer-issued operator proof, exchanged once for a browser-session grant. */
class SetupClaim
{
    private static string $path = __DIR__ . '/../../.setup-claim';
    private const LIFETIME = 86400;

    public static function required(): bool
    {
        $state = DB::row('SELECT `completed` FROM `SetupState` WHERE `setupId` = 1', \stdClass::class);
        return $state === null || !(bool) $state -> completed;
    }

    /** Only the CLI installer may mint or replace operator proof. */
    public static function issue(?string $owner = null): string
    {
        if (PHP_SAPI !== 'cli') {
            throw new \LogicException('Setup codes can only be issued by the installer.');
        }
        $code = bin2hex(random_bytes(32));
        self::locked(static function ($file) use ($code, $owner): void {
            if (!chmod(self::$path, 0600) || ($owner !== null && !chown(self::$path, $owner))) {
                throw new \RuntimeException('Could not secure the setup claim file.');
            }
            self::write($file, ['codeHash' => hash('sha256', $code), 'expiresAt' => time() + self::LIFETIME]);
        }, true);
        return $code;
    }

    private static function locked(callable $work, bool $create = false): mixed
    {
        $mask = umask(0077);
        try {
            $file = @fopen(self::$path, $create ? 'c+' : 'r+');
        } finally {
            umask($mask);
        }
        if ($file === false) {
            throw new \RuntimeException('Run the installer with --setup-code to authorize setup.');
        }
        try {
            if (!flock($file, LOCK_EX | LOCK_NB)) {
                throw new \RuntimeException('Setup is busy. Please try again.');
            }
            return $work($file);
        } finally {
            fclose($file);
        }
    }

    private static function read($file): array
    {
        rewind($file);
        $data = json_decode((string) stream_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private static function write($file, array $data): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        rewind($file);
        if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) {
            throw new \RuntimeException('Could not save setup authorization.');
        }
    }

    private static function matches(array $state): bool
    {
        $grant = $_SESSION['setupGrant'] ?? null;
        return is_string($grant) && is_string($state['grantHash'] ?? null)
            && ($state['expiresAt'] ?? 0) > time()
            && hash_equals($state['grantHash'], hash('sha256', $grant));
    }

    public static function authorized(): bool
    {
        try {
            return self::locked(static fn ($file): bool => self::matches(self::read($file)));
        } catch (\RuntimeException $exception) {
            return false;
        }
    }

    public static function accept(string $code): bool
    {
        if (preg_match('/\A[0-9a-f]{64}\z/', $code) !== 1) {
            return false;
        }
        return self::locked(static function ($file) use ($code): bool {
            $state = self::read($file);
            if (($state['expiresAt'] ?? 0) <= time() || !is_string($state['codeHash'] ?? null)
                || !hash_equals($state['codeHash'], hash('sha256', $code))) {
                return false;
            }
            $grant = bin2hex(random_bytes(32));
            self::write($file, ['grantHash' => hash('sha256', $grant), 'expiresAt' => time() + self::LIFETIME]);
            if (session_status() === PHP_SESSION_ACTIVE && !session_regenerate_id(true)) {
                throw new \RuntimeException('Could not renew the setup session.');
            }
            $_SESSION['setupGrant'] = $grant;
            return true;
        });
    }

    /** Render an ordinary POST form until this browser has the operator grant. */
    public static function gate(): void
    {
        if (!ServerURL::isHTTPS()) {
            http_response_code(400);
            echo 'Open this page over HTTPS before entering a setup code.';
            exit;
        }
        if (self::authorized()) {
            return;
        }
        $error = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $csrf = $_POST['CSRFToken'] ?? null;
            $code = $_POST['setupCode'] ?? null;
            try {
                if (is_string($csrf) && CSRF::verify($csrf) && is_string($code) && self::accept(trim($code))) {
                    return;
                }
                $error = 'The setup code is invalid, expired, or already used.';
            } catch (\RuntimeException $exception) {
                $error = $exception -> getMessage();
            }
        }
        header('Cache-Control: no-store');
        $page = Page::create('Authorize Setup');
        $page -> addContent(new Paragraph('Enter the single-use code printed by the installer. Only the server operator can authorize configuration and create the first administrator.'));
        if ($error !== null) {
            $page -> addContent(new ErrorList([$error]));
        }
        $page -> addContent(new SetupClaimForm());
        $page -> send();
        exit;
    }

    /** Serialize initial registration and consume the grant with the new administrator. */
    public static function register(callable $insert): mixed
    {
        return DB::transaction(static function () use ($insert): mixed {
            $state = DB::row('SELECT `completed` FROM `SetupState` WHERE `setupId` = 1 FOR UPDATE', \stdClass::class);
            if ($state === null) {
                throw new \RuntimeException('The installer must initialize setup before registration.');
            }
            if ((bool) $state -> completed) {
                return $insert(false);
            }
            return self::locked(static function ($file) use ($insert): mixed {
                if (!self::matches(self::read($file))) {
                    throw new \RuntimeException('The server operator must claim this installation before registration.');
                }
                $result = $insert(true);
                DB::run('UPDATE `SetupState` SET `completed` = 1 WHERE `setupId` = 1');
                // The committed state is authoritative even if this cleanup fails.
                DB::afterCommit(static function (): void {
                    unset($_SESSION['setupGrant']);
                    self::locked(static fn ($claim) => self::write($claim, []));
                });
                return $result;
            });
        });
    }

    /** Serialize configuration and recheck .env while holding the same claim lock. */
    public static function configure(callable $configure): mixed
    {
        return self::locked(static function ($file) use ($configure): mixed {
            if (!self::matches(self::read($file)) || is_file(__DIR__ . '/../../.env')) {
                throw new \RuntimeException('Setup is no longer available for this request.');
            }
            return $configure();
        });
    }
}
