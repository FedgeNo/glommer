<?php

declare(strict_types=1);

/**
 * Proves the whole report pipeline against the running site: POSTs a report
 * carrying a unique marker to the site's own /api/csp-report, exactly as a
 * browser acting on the report-uri directive would, then finds the marker in
 * the live CSPReports table over the indexed blockedURI column.
 *
 * DB::connection() is no use for the lookup - during a sudo run it points at
 * the throwaway test database while the web server writes to the real one -
 * so the check opens its own connection from .env, which also makes this
 * skip honestly on an unprivileged run that cannot read the root-tightened
 * file. The manufactured row is deleted afterwards; it reported nothing.
 */
class CSPReportEndpointTest extends TestCase
{
    public function testAReportRoundTripsThroughTheLiveEndpoint(): void
    {
        $site_url = rtrim((string) Config::get('siteURL'), '/');

        if ($site_url === '') {
            throw new TestSkippedException('siteURL is not configured, so there is no live endpoint to submit to');
        }

        // The token is what the fulltext lookup below searches for. Fulltext
        // tokenizes on punctuation, so inside the URL it survives as one word
        // and the search must use the bare token, not the whole address.
        $token = bin2hex(random_bytes(16));
        $marker = 'https://csp-selftest.invalid/' . $token;
        $report = (string) json_encode([
            'csp-report' => [
                'document-uri' => $site_url . '/',
                'violated-directive' => 'img-src',
                'blocked-uri' => $marker,
            ],
        ]);

        $curl = curl_init($site_url . '/api/csp-report');
        $this -> assertTrue($curl !== false, 'curl_init failed');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $report,
            CURLOPT_HTTPHEADER => ['Content-Type: application/csp-report', 'User-Agent: CSPReportEndpointTest'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            // A loopback call to the box's own server; dev's certificate is
            // self-signed, and authenticating ourselves to ourselves proves
            // nothing this test is after.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            throw new TestSkippedException('The site is not answering at ' . $site_url . ' - ' . $curl_error);
        }

        $this -> assertSame(204, $status, 'Expected 204 from the report endpoint, got ' . $status);

        $live = $this -> liveConnection();

        try {
            $select = mysqli_prepare($live, '
SELECT `reportId`, `report`, `userAgent`
    FROM `CSPReports`
    WHERE MATCH(`report`) AGAINST(? IN BOOLEAN MODE)
');
            mysqli_stmt_bind_param($select, 's', $token);
            mysqli_stmt_execute($select);
            $rows = mysqli_fetch_all(mysqli_stmt_get_result($select), MYSQLI_ASSOC);

            $this -> assertSame(1, count($rows), 'Expected exactly one stored report for the marker, found ' . count($rows));
            $this -> assertSame($report, $rows[0]['report'], 'The stored report is not the verbatim submitted body');
            $this -> assertSame('CSPReportEndpointTest', $rows[0]['userAgent']);

            $delete = mysqli_prepare($live, '
DELETE FROM `CSPReports`
    WHERE `reportId` = ?
');
            $report_id = (int) $rows[0]['reportId'];
            mysqli_stmt_bind_param($delete, 'i', $report_id);
            mysqli_stmt_execute($delete);
        } finally {
            mysqli_close($live);
        }
    }

    /**
     * The live database, from .env directly - process env may already carry
     * TestDatabase's throwaway overrides, so only the file is trustworthy
     * here.
     */
    private function liveConnection(): \mysqli
    {
        $lines = @file(Env::path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new TestSkippedException('.env is not readable from this run, so the live database cannot be checked - re-run with sudo');
        }

        $values = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = Env::stripQuotes(trim($value));
        }

        try {
            return mysqli_connect(
                $values['DB_HOST'] ?? '127.0.0.1',
                $values['DB_USERNAME'] ?? 'glommer',
                $values['DB_PASSWORD'] ?? '',
                $values['DB_DATABASE'] ?? 'glommer',
                (int) ($values['DB_PORT'] ?? 3306)
            );
        } catch (\mysqli_sql_exception $exception) {
            throw new TestSkippedException('Could not reach the live database with .env credentials - ' . $exception -> getMessage());
        }
    }
}
