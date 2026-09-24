<?php

declare(strict_types=1);

class DBHostTest extends TestCase
{
    public function testOrdinaryHostsArePreserved(): void
    {
        foreach (['localhost', '127.0.0.1', 'db.example.test', '::1', '[::1]', 'primary'] as $host) {
            $this -> assertSame($host, DB::validatedHost($host));
        }
    }

    public function testPersistentPrefixesAreRejected(): void
    {
        foreach (['p:localhost', 'P:localhost', 'p:127.0.0.1', 'P:[::1]', 'p:', 'P:'] as $host) {
            $rejected = false;
            try {
                DB::validatedHost($host);
            } catch (\InvalidArgumentException $exception) {
                $rejected = true;
                $this -> assertTrue(str_contains($exception -> getMessage(), 'DB_HOST'));
            }
            $this -> assertTrue($rejected, 'Persistent hosts must be rejected before connecting.');
        }
    }

    public function testConfigurationRejectsPersistentHostsAfterReload(): void
    {
        // Load .env before overriding this process's environment.
        Config::get('host');
        $original = getenv('DB_HOST');
        try {
            foreach (['p:localhost', 'P:localhost'] as $host) {
                putenv('DB_HOST=' . $host);
                Config::reload();
                $rejected = false;
                try {
                    Config::get('host');
                } catch (\InvalidArgumentException) {
                    $rejected = true;
                }
                $this -> assertTrue($rejected);
            }
        } finally {
            putenv($original === false ? 'DB_HOST' : 'DB_HOST=' . $original);
            Config::reload();
        }
    }
}
