<?php

declare(strict_types=1);

// The test suite's entry point: `php bin/run-tests.php`. No external test
// runner (no PHPUnit/Composer) - a hand-rolled reflection-based discovery
// loop, same instinct as everywhere else in this app that reaches for "we
// write it ourselves" over a dependency. A test class is anything under
// tests/ extending TestCase; a test case is any public method on it named
// test*. Exits non-zero on any failure, so this is CI-usable as-is.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/../tests/TestCase.php';

$tests_dir = __DIR__ . '/../tests';

foreach (glob($tests_dir . '/*Test.php') ?: [] as $file) {
    require $file;
}

$test_classes = array_filter(
    get_declared_classes(),
    fn (string $class) => is_subclass_of($class, TestCase::class)
);

sort($test_classes);

if ($test_classes === []) {
    fwrite(STDERR, "No test classes found under tests/ (expected files named *Test.php defining a class extending TestCase).\n");
    exit(1);
}

$total = 0;
$failures = [];
$started_at = microtime(true);

foreach ($test_classes as $class) {
    $instance = new $class();
    $methods = array_filter(
        get_class_methods($class),
        fn (string $method) => str_starts_with($method, 'test')
    );

    sort($methods);

    foreach ($methods as $method) {
        $total++;
        $label = $class . '::' . $method;

        try {
            $instance -> $method();
            echo "  \033[32mPASS\033[0m  {$label}\n";
        } catch (AssertionFailedException $exception) {
            echo "  \033[31mFAIL\033[0m  {$label}\n";
            $failures[] = ['label' => $label, 'message' => $exception -> getMessage()];
        } catch (\Throwable $exception) {
            echo "  \033[31mERROR\033[0m {$label}\n";
            $failures[] = ['label' => $label, 'message' => get_class($exception) . ': ' . $exception -> getMessage()];
        }
    }
}

$elapsed_ms = (int) round((microtime(true) - $started_at) * 1000);

echo "\n";

if ($failures !== []) {
    echo "Failures:\n";

    foreach ($failures as $failure) {
        echo "  {$failure['label']}\n    {$failure['message']}\n";
    }

    echo "\n";
}

$passed = $total - count($failures);
echo "{$passed}/{$total} passed ({$elapsed_ms}ms)\n";

exit($failures === [] ? 0 : 1);
