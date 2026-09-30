<?php

declare(strict_types=1);

/**
* Boot Layout Test (v2.1.1)
*
* Run from the framework folder before every release:
*   php tests/boot_layout_test.php
*
* What it checks:
* - Builds throwaway project trees in the system temp dir: a symlink to
*   this framework/ next to a minimal app/ (bootstrap.php, Config/config.php, .env).
* - Correct layout (APP_BASE_DIR = project root): boot gets past the layout
*   guard, .env and config, and stops only at Database::connect (the fixture's
*   DSN points at a closed port), so no real database is needed.
* - Legacy layout (APP_BASE_DIR = app/): boot fails with the layout guard's
*   actionable message, not ".env file not found at .../app/app/.env".
*
* Each boot runs in a separate PHP process because bootstrap.php defines
* constants and registers an autoloader. Exits non-zero on any failure.
*/

const DS = DIRECTORY_SEPARATOR;

$frameworkDir = realpath(__DIR__ . '/..');
if ($frameworkDir === false) {
	fwrite(STDERR, "Could not resolve framework directory." . PHP_EOL);
	exit(1);
}

$root = sys_get_temp_dir() . DS . 'frankphp-boot-layout-' . bin2hex(random_bytes(4));
buildFixture($root, $frameworkDir);

$failures = 0;

try {
	// Correct v2.0+ layout: index.php does dirname(__DIR__, 2) from app/public.
	$output = bootFrom($root, $root);
	$failures += check(
		'root layout passes the guard and reaches Database::connect',
		str_contains($output, 'PDOException')
			&& !str_contains($output, 'APP_BASE_DIR points at')
			&& !str_contains($output, 'Application bootstrap not found')
			&& !str_contains($output, '.env file not found'),
		$output
	);

	// Legacy pre-2.0 layout: index.php does dirname(__DIR__, 1) from app/public.
	$output = bootFrom($root . DS . 'app', $root);
	$failures += check(
		'app/ layout fails with the actionable layout message',
		str_contains($output, 'APP_BASE_DIR points at the app/ folder')
			&& str_contains($output, "dirname(__DIR__, 2)")
			&& !str_contains($output, '.env file not found'),
		$output
	);

	// Neither layout (e.g. a typo'd path): generic guard message.
	$output = bootFrom($root . DS . 'nowhere', $root);
	$failures += check(
		'unknown layout fails with the generic guard message',
		str_contains($output, 'Application bootstrap not found'),
		$output
	);
} finally {
	deleteDirectory($root);
}

echo PHP_EOL . ($failures === 0 ? 'All boot layout checks passed.' : "{$failures} check(s) failed.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);

/**
* Creates <root>/framework (symlink) and a minimal <root>/app.
*/
function buildFixture(string $root, string $frameworkDir): void
{
	mkdir($root . DS . 'app' . DS . 'Config', 0777, true);
	mkdir($root . DS . 'app' . DS . 'public', 0777, true);

	if (!symlink($frameworkDir, $root . DS . 'framework')) {
		fwrite(STDERR, "Could not symlink framework into fixture." . PHP_EOL);
		exit(1);
	}

	file_put_contents($root . DS . 'app' . DS . '.env', "APP_ENV=test\n");
	file_put_contents($root . DS . 'app' . DS . 'bootstrap.php', "<?php\nreturn \$router;\n");
	file_put_contents(
		$root . DS . 'app' . DS . 'Config' . DS . 'config.php',
		"<?php\nreturn ['db' => ['dsn' => 'mysql:host=127.0.0.1;port=1', 'user' => 'x', 'pass' => 'x', 'database' => 'x']];\n"
	);
}

/**
* Boots framework/bootstrap.php in a child PHP process with the given
* APP_BASE_DIR, the same way app/public/index.php does. Returns combined output.
*/
function bootFrom(string $appBaseDir, string $root): string
{
	$code = sprintf(
		'define("APP_BASE_DIR", %s); $r = require %s; echo get_class($r), PHP_EOL;',
		var_export($appBaseDir, true),
		var_export($root . DS . 'framework' . DS . 'bootstrap.php', true)
	);

	$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -r ' . escapeshellarg($code) . ' 2>&1';

	return (string) shell_exec($command);
}

function check(string $label, bool $passed, string $output): int
{
	echo ($passed ? 'PASS  ' : 'FAIL  ') . $label . PHP_EOL;

	if (!$passed) {
		echo '      output: ' . str_replace("\n", "\n              ", trim($output)) . PHP_EOL;
	}

	return $passed ? 0 : 1;
}

/**
* Removes the fixture. Unlinks the framework symlink without following it.
*/
function deleteDirectory(string $dir): void
{
	if (is_link($dir) || is_file($dir)) {
		unlink($dir);
		return;
	}

	if (!is_dir($dir)) {
		return;
	}

	foreach (scandir($dir) as $item) {
		if ($item !== '.' && $item !== '..') {
			deleteDirectory($dir . DS . $item);
		}
	}

	rmdir($dir);
}
