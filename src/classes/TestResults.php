<?php

declare(strict_types=1);

/**
 * The admin test-runner page's body: runs the project's own test suite in a
 * subprocess and shows the runner's output, so an admin can check the build
 * from the browser. Database-backed tests need the DB root account and are
 * skipped here (a web request isn't root) - the runner says so in its output;
 * the full suite runs from the CLI with `sudo php bin/run-tests.php`.
 */
class TestResults extends HTMLObject
{
    public ?string $class = 'TestResults Card';

    public function toDOM(): \DOMElement
    {
        $script = __DIR__ . '/../../bin/run-tests.php';

        exec('php ' . escapeshellarg($script) . ' 2>&1', $lines, $exit_code);

        // The runner colours its PASS/FAIL markers with ANSI escapes for the
        // terminal; strip them so the browser shows plain text.
        $output = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $lines));

        $badge = new Div();
        $badge -> class = $exit_code === 0 ? 'TestResultsBadge TestResultsPass' : 'TestResultsBadge TestResultsFail';
        $badge -> contents[] = $exit_code === 0 ? 'Passing' : 'Failing';
        $this -> contents[] = $badge;

        $report = new HTMLObject();
        $report -> tagName = 'pre';
        $report -> class = 'TestResultsOutput';
        $report -> contents[] = $output !== '' ? $output : '(no output)';
        $this -> contents[] = $report;

        return parent::toDOM();
    }
}
