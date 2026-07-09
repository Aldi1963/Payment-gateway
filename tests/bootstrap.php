<?php
/**
 * Test bootstrap + tiny dependency-free test framework.
 *
 * This project runs in an environment WITHOUT Composer/Packagist access and
 * WITHOUT a bundled test database, so we cannot rely on PHPUnit/Pest. This
 * bootstrap provides just enough of a harness (test/assert functions) to run
 * fast, isolated unit tests with plain `php`.
 */

// --- CLI-safe request environment (mirrors test_all.php) ---------------------
@ini_set('session.use_cookies', '0');
@ini_set('session.use_only_cookies', '0');
@ini_set('session.cache_limiter', '');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF']       = '/tests/run.php';
$_SERVER['REQUEST_URI']    = '/tests/run.php';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestRunner/1.0';

define('APP_ROOT', dirname(__DIR__));

// Core helpers (defines base_path, setting, config, now, etc.). Loading these
// files does NOT open a DB connection; connections are lazy.
require_once APP_ROOT . '/app/Database.php';
require_once APP_ROOT . '/app/Helpers.php';

// -----------------------------------------------------------------------------
// Minimal test framework
// -----------------------------------------------------------------------------
$GLOBALS['__test_passed']  = 0;
$GLOBALS['__test_failed']  = 0;
$GLOBALS['__test_results'] = [];

/** Register + run a single test case. The callable should perform assertions. */
function test(string $name, callable $fn): void
{
    try {
        $fn();
        $GLOBALS['__test_passed']++;
        $GLOBALS['__test_results'][] = ['PASS', $name, ''];
    } catch (\Throwable $e) {
        $GLOBALS['__test_failed']++;
        $GLOBALS['__test_results'][] = ['FAIL', $name, $e->getMessage()];
    }
}

final class AssertionFailed extends \RuntimeException {}

function fail(string $message): void
{
    throw new AssertionFailed($message);
}

/** Strict equality assertion with a readable diff message. */
function assert_equals(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        fail(($msg !== '' ? $msg . ' — ' : '')
            . 'expected ' . var_export($expected, true)
            . ' but got ' . var_export($actual, true));
    }
}

function assert_true(bool $cond, string $msg = ''): void
{
    if ($cond !== true) {
        fail($msg !== '' ? $msg : 'expected true');
    }
}

function assert_false(bool $cond, string $msg = ''): void
{
    if ($cond !== false) {
        fail($msg !== '' ? $msg : 'expected false');
    }
}

/**
 * Run a callable with a temporary set of overridden dynamic settings, then
 * always restore the previous override map. Relies on the guarded test seam
 * in setting() (app/Helpers.php).
 *
 * @param array<string,mixed> $overrides
 */
function with_settings(array $overrides, callable $fn): void
{
    $prev = $GLOBALS['__TEST_SETTINGS_OVERRIDE'] ?? null;
    $GLOBALS['__TEST_SETTINGS_OVERRIDE'] = $overrides;
    try {
        $fn();
    } finally {
        if ($prev === null) {
            unset($GLOBALS['__TEST_SETTINGS_OVERRIDE']);
        } else {
            $GLOBALS['__TEST_SETTINGS_OVERRIDE'] = $prev;
        }
    }
}
