<?php
/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║              NMIMS Quiz App — Endpoint Test Suite               ║
 * ╠══════════════════════════════════════════════════════════════════╣
 * ║  Usage:                                                          ║
 * ║    php tests/test.php                                            ║
 * ║    php tests/test.php --verbose                                  ║
 * ║    php tests/test.php --filter=Auth                              ║
 * ║    php tests/test.php --base-url=http://localhost:8080           ║
 * ╚══════════════════════════════════════════════════════════════════╝
 *
 * Requires:
 *   - PHP >= 7.4 with the cURL and PDO_MySQL extensions enabled
 *   - The NMIMS Quiz App server running at BASE_URL (default: http://localhost:8080)
 *   - Valid DB credentials matching config/database.php
 */

declare(strict_types=1);

// ─── Parse CLI args ───────────────────────────────────────────────────────────
$options = getopt('', ['verbose', 'base-url:', 'filter:']);
$VERBOSE  = isset($options['verbose']);
$BASE_URL = $options['base-url'] ?? 'http://localhost:8080';
$FILTER   = $options['filter']   ?? null;

// ─── Sanity checks ────────────────────────────────────────────────────────────
if (!extension_loaded('curl')) {
    die("ERROR: PHP cURL extension is required. Enable it in php.ini.\n");
}
if (!extension_loaded('pdo_mysql')) {
    die("ERROR: PHP PDO MySQL extension is required. Enable it in php.ini.\n");
}

// ─── Colour helpers ───────────────────────────────────────────────────────────
function colorize(string $text, string $color): string
{
    $codes = [
        'red'     => "\033[31m",
        'green'   => "\033[32m",
        'yellow'  => "\033[33m",
        'cyan'    => "\033[36m",
        'bold'    => "\033[1m",
        'reset'   => "\033[0m",
    ];
    $c = $codes[$color] ?? '';
    return $c . $text . $codes['reset'];
}

// ─── Assertion helpers ────────────────────────────────────────────────────────

/**
 * Assert two values are equal (strict).
 */
function assert_eq(mixed $actual, mixed $expected, string $msg = ''): void
{
    if ($actual !== $expected) {
        throw new AssertionError(
            $msg
                ? "$msg\n         Expected: " . json_encode($expected)
                        . "\n           Actual: " . json_encode($actual)
                : "Expected " . json_encode($expected) . " got " . json_encode($actual)
        );
    }
}

/**
 * Assert value is in list.
 */
function assert_in(mixed $actual, array $list, string $msg = ''): void
{
    if (!in_array($actual, $list, true)) {
        throw new AssertionError(
            $msg
                ? "$msg (got " . json_encode($actual) . ")"
                : "Expected one of " . json_encode($list) . " got " . json_encode($actual)
        );
    }
}

/**
 * Assert array key exists.
 */
function assert_key(mixed $arr, string $key, string $msg = ''): void
{
    if (!is_array($arr) || !array_key_exists($key, $arr)) {
        throw new AssertionError(
            $msg ?: "Expected key '$key' to exist in response"
        );
    }
}

/**
 * Assert a boolean is true.
 */
function assert_true(bool $value, string $msg = ''): void
{
    if (!$value) {
        throw new AssertionError($msg ?: 'Assertion failed (expected true)');
    }
}

// ─── Test case helper ─────────────────────────────────────────────────────────

/**
 * Wraps a named callable into a test-result array.
 * Returns ['name', 'pass', 'error'].
 */
function test(string $name, callable $fn): array
{
    try {
        $fn();
        return ['name' => $name, 'pass' => true, 'error' => null];
    } catch (AssertionError $e) {
        return ['name' => $name, 'pass' => false, 'error' => 'Assertion: ' . $e->getMessage()];
    } catch (Throwable $e) {
        return ['name' => $name, 'pass' => false, 'error' => get_class($e) . ': ' . $e->getMessage()];
    }
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────
$dir = __DIR__;
require_once "$dir/TestClient.php";
require_once "$dir/bootstrap.php";

// ─── Auto-detect the server ───────────────────────────────────────────────────
echo colorize("\n═══ NMIMS Quiz App — Endpoint Test Suite ═══\n\n", 'bold');
echo "Base URL : $BASE_URL\n";
echo "Filter   : " . ($FILTER ?? '(none — running all)') . "\n";
echo "Verbose  : " . ($VERBOSE ? 'yes' : 'no') . "\n\n";

// Quick connectivity check
$ch = curl_init($BASE_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 0) {
    echo colorize("ERROR: Cannot reach $BASE_URL — is the server running?\n", 'red');
    echo "Hint: start it with:  php -S localhost:8080 router.php\n\n";
    exit(1);
}
echo colorize("✓ Server reachable at $BASE_URL (HTTP $httpCode)\n\n", 'green');

// ─── Setup test fixtures ──────────────────────────────────────────────────────
try {
    bootstrap_setup();
} catch (Throwable $e) {
    echo colorize("FATAL: bootstrap_setup() failed — " . $e->getMessage() . "\n", 'red');
    exit(1);
}
echo "\n";

// ─── Load test classes ────────────────────────────────────────────────────────
require_once "$dir/AuthTest.php";
require_once "$dir/AdminTest.php";
require_once "$dir/FacultyTest.php";
require_once "$dir/StudentTest.php";
require_once "$dir/SharedTest.php";

// ─── Build the HTTP client ────────────────────────────────────────────────────
$http = new TestClient($BASE_URL, $VERBOSE);

// ─── Collect & run all test suites ───────────────────────────────────────────
$suites = [
    'Auth'    => new AuthTest($http),
    'Admin'   => new AdminTest($http),
    'Faculty' => new FacultyTest($http),
    'Student' => new StudentTest($http),
    'Shared'  => new SharedTest($http),
];

$allResults  = [];
$totalPass   = 0;
$totalFail   = 0;

foreach ($suites as $suiteName => $suite) {
    if ($FILTER && stripos($suiteName, $FILTER) === false) {
        continue;
    }

    echo colorize("── $suiteName ──────────────────────────────────────────\n", 'cyan');

    $results = $suite->run();

    foreach ($results as $result) {
        $icon  = $result['pass'] ? colorize('  ✓', 'green') : colorize('  ✗', 'red');
        $label = $result['pass']
            ? colorize($result['name'], 'green')
            : colorize($result['name'], 'red');

        echo "$icon  $label\n";

        if (!$result['pass']) {
            $totalFail++;
            $lines = explode("\n", $result['error']);
            foreach ($lines as $line) {
                echo colorize("       $line\n", 'yellow');
            }
        } else {
            $totalPass++;
        }

        $allResults[] = array_merge($result, ['suite' => $suiteName]);
    }

    echo "\n";
}

// ─── Teardown ─────────────────────────────────────────────────────────────────
bootstrap_teardown();

// ─── Summary ──────────────────────────────────────────────────────────────────
$total = $totalPass + $totalFail;
echo "\n" . colorize("══════════════════ RESULTS ══════════════════\n", 'bold');
echo "  Total  : $total\n";
echo "  Passed : " . colorize((string)$totalPass, 'green') . "\n";
echo "  Failed : " . colorize((string)$totalFail, $totalFail > 0 ? 'red' : 'green') . "\n";
echo colorize("═════════════════════════════════════════════\n\n", 'bold');

if ($totalFail > 0) {
    echo colorize("FAILED TESTS:\n", 'red');
    foreach ($allResults as $r) {
        if (!$r['pass']) {
            echo colorize("  ✗ [{$r['suite']}] {$r['name']}\n", 'red');
            echo colorize("    " . $r['error'] . "\n\n", 'yellow');
        }
    }
    exit(1);
}

echo colorize("All tests passed! 🎉\n\n", 'green');
exit(0);
