<?php

declare(strict_types=1);

/**
 * The installed site's real HTTP boundary.
 *
 * Payload construction and database effects belong to the ordinary unit and
 * database tests. This one crosses Apache, rewriting, bootstrap, negotiation,
 * response headers and JSON encoding together, which none of those can prove.
 * A member name is explicit because a generic installation has no account
 * whose existence a test may assume.
 */
class ActivityPubHTTPTest extends TestCase
{
    public function testTheInstalledActivityPubSurface(): void
    {
        $this -> requireInstallation();

        $username = (string) getenv('ACTIVITYPUB_TEST_USERNAME');

        if ($username === '') {
            throw new TestSkippedException('pass --activitypub-username=<local member> to test the installed ActivityPub surface');
        }

        $command = [
            PHP_BINARY,
            __DIR__ . '/../bin/run-activitypub-tests.php',
            '--base=' . rtrim((string) Config::get('siteURL'), '/'),
            '--username=' . $username,
            '--quiet',
        ];

        if (getenv('ACTIVITYPUB_TEST_INSECURE') !== false) {
            $command[] = '--insecure';
        }

        $pipes = [];
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            throw new AssertionFailedException('Could not start the ActivityPub HTTP checks.');
        }

        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        $this -> assertSame(0, $status, trim((string) $output . "\n" . (string) $errors));
    }
}
