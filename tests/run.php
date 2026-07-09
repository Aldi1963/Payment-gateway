<?php
/**
 * Dependency-free unit test runner.
 *
 * Usage:  php tests/run.php
 *
 * Discovers every *Test.php under tests/unit, executes the registered tests,
 * prints a summary and exits non-zero if any test fails (CI-friendly).
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== PAYMENT GATEWAY - UNIT TESTS ===\n\n";

$testFiles = glob(__DIR__ . '/unit/*Test.php') ?: [];
sort($testFiles);

foreach ($testFiles as $file) {
    require_once $file;
}

// Print results grouped in registration order
foreach ($GLOBALS['__test_results'] as [$status, $name, $detail]) {
    if ($status === 'PASS') {
        echo "  [PASS] {$name}\n";
    } else {
        echo "  [FAIL] {$name}\n";
        if ($detail !== '') {
            echo "         -> {$detail}\n";
        }
    }
}

$passed = $GLOBALS['__test_passed'];
$failed = $GLOBALS['__test_failed'];
$total  = $passed + $failed;

echo "\n" . str_repeat('-', 60) . "\n";
echo "  TOTAL:  {$total} tests\n";
echo "  PASSED: {$passed}\n";
echo "  FAILED: {$failed}\n";
echo str_repeat('-', 60) . "\n\n";

if ($failed > 0) {
    echo "  SOME TESTS FAILED\n";
    exit(1);
}

echo "  ALL TESTS PASSED\n";
exit(0);
