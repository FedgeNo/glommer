<?php

declare(strict_types=1);

class TestResults extends Div
{
    public ?string $class = 'TestResults Card';

    public function toDOM(): \DOMElement
    {
        // ----- PHP tests (always run) -----
        $phpScript = __DIR__ . '/../../bin/run-tests.php';
        exec('php ' . escapeshellarg($phpScript) . ' 2>&1', $phpLines, $phpExitCode);
        $phpOutput = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $phpLines));

        $phpBadge = new Div();
        $phpBadge -> class = $phpExitCode === 0
            ? 'TestResultsBadge TestResultsPass'
            : 'TestResultsBadge TestResultsFail';
        $phpBadge -> contents[] = 'PHP: ' . ($phpExitCode === 0 ? 'Passing' : 'Failing');
        $this -> contents[] = $phpBadge;

        $phpReport = new Div();
        $phpReport -> tagName = 'pre';
        $phpReport -> class = 'TestResultsOutput';
        $phpReport -> contents[] = $phpOutput !== '' ? $phpOutput : '(no output)';
        $this -> contents[] = $phpReport;

        // ----- JavaScript tests (only if Node.js is installed) -----
        $hasNode = trim(shell_exec('command -v node 2>/dev/null') ?? '') !== '';

        if (!$hasNode) {
            $this -> contents[] = new Paragraph(
                'Node.js is not installed. Install Node.js to enable JavaScript tests.'
            );
            return parent::toDOM();
        }

        $nodeModulesPath = __DIR__ . '/../../node_modules';
        $hasNodeModules = is_dir($nodeModulesPath);

        if (!$hasNodeModules) {
            $this -> contents[] = new Paragraph(
                'Run `npm install` in the document root to enable JavaScript tests.'
            );
            return parent::toDOM();
        }

        // Node and dependencies present – run JS tests
        $jsScript = __DIR__ . '/../../bin/run-js-tests.js';
        exec('node ' . escapeshellarg($jsScript) . ' 2>&1', $jsLines, $jsExitCode);
        $jsOutput = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $jsLines));

        // If Node crashed before any test ran (V8_Fatal), show a notice instead of a false failure
        if ($jsExitCode !== 0 && strpos($jsOutput, 'V8_Fatal') !== false) {
            $this -> contents[] = new Paragraph(
                'JavaScript tests could not be started. Node.js encountered a fatal error, ' .
                'most likely due to SELinux restrictions on the web server. ' .
                'This will be handled by the installer in a future update.'
            );
        } else {
            $jsBadge = new Div();
            $jsBadge -> class = $jsExitCode === 0
                ? 'TestResultsBadge TestResultsPass'
                : 'TestResultsBadge TestResultsFail';
            $jsBadge -> contents[] = 'JS: ' . ($jsExitCode === 0 ? 'Passing' : 'Failing');
            $this -> contents[] = $jsBadge;

            $jsReport = new Div();
            $jsReport -> tagName = 'pre';
            $jsReport -> class = 'TestResultsOutput';
            $jsReport -> contents[] = $jsOutput !== '' ? $jsOutput : '(no output)';
            $this -> contents[] = $jsReport;
        }

        return parent::toDOM();
    }
}
