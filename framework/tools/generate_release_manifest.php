<?php

declare(strict_types=1);

/**
* Release Manifest Generator
*
* Suggested location:
*   /tools/generate_release_manifest.php
*
* Run from project root:
*   php tools/generate_release_manifest.php
*
* What it does:
* - Reads current version from /VERSION
* - Scans project files and hashes them
* - Compares against the most recent previous release snapshot
* - Extracts routes from bootstrap.php
* - Extracts basic schema signals from sql/schema.core.sql
* - Writes /RELEASE_MANIFEST.json
* - Writes snapshots into /releases/{version}/
*
* Notes:
* - This is intentionally conservative and deterministic.
* - It does not try to infer meaning from code beyond file, route, and schema changes.
* - You should still review summary, breaking_changes, and ai_notes manually.
*/

const DS = DIRECTORY_SEPARATOR;

$root = realpath(__DIR__ . '/..');
if ($root === false) {
	fail('Could not resolve project root.');
}

$config = [
	'root' => $root,
	'version_file' => $root . DS . 'VERSION',
	'manifest_file' => $root . DS . 'RELEASE_MANIFEST.json',
	'bootstrap_file' => $root . DS . 'bootstrap.php',
	'schema_file' => $root . DS . 'sql' . DS . 'schema.core.sql',
	'releases_dir' => $root . DS . 'releases',

	// Directories/files to ignore when indexing
	'exclude_paths' => [
		'.git',
		'.idea',
		'.vscode',
		'node_modules',
		'vendor',
		'releases',
		'dist', // build output — v2.1.0 fix: 2.0.1's manifest wrongly listed dist/ files as added
	],

	// Ignore common generated/junk files
	'exclude_filenames' => [
		'.DS_Store',
		'Thumbs.db',
	],

	// Optional: ignore this script itself in the diff
	'exclude_relative_files' => [
		'tools/generate_release_manifest.php',
	],
];

main($config);

/**
* Main entry point.
*/
function main(array $config): void
{
	ensureDirectory($config['releases_dir']);

	$version = readVersion($config['version_file']);
	$releaseDate = date('Y-m-d');

	$currentIndex = buildFileIndex($config['root'], $config);
	$currentRoutes = extractRoutes($config['bootstrap_file']);
	$currentSchema = extractSchemaSignals($config['schema_file']);

	[$previousVersion, $previousSnapshot] = loadPreviousSnapshot($config['releases_dir'], $version);

	// Apply today's exclusions to the previous snapshot too, so paths that
	// older snapshots indexed by mistake (e.g. dist/ in 2.0.1) don't show up
	// as "removed".
	$previousIndex = array_filter(
	$previousSnapshot['file_index'] ?? [],
	fn (string $path): bool => !isExcludedPath($path, $config['exclude_paths']),
	ARRAY_FILTER_USE_KEY
	);

	$diff = diffFileIndexes(
	$previousIndex,
	$currentIndex
	);

	$routeDiff = diffRoutes(
	$previousSnapshot['routes'] ?? [],
	$currentRoutes
	);

	$schemaDiff = diffSchemaSignals(
	$previousSnapshot['schema'] ?? defaultSchemaSignals(),
	$currentSchema
	);

	$manifest = [
		'version' => $version,
		'release_date' => $releaseDate,
		'summary' => buildSummaryStub($diff, $routeDiff, $schemaDiff),
		'previous_version' => $previousVersion,
		'changes' => [
			'added' => array_values($diff['added']),
			'changed' => array_values($diff['changed']),
			'removed' => array_values($diff['removed']),
		],
		'route_changes' => [
			'added' => array_values($routeDiff['added']),
			'removed' => array_values($routeDiff['removed']),
		],
		'database_changes' => [
			'tables_added' => array_values($schemaDiff['tables_added']),
			'tables_removed' => array_values($schemaDiff['tables_removed']),
			'alter_statements_added' => array_values($schemaDiff['alters_added']),
		],
		'breaking_changes' => [],
		'ai_notes' => [
			'Review the changed files list for any important behavioural changes not visible from file diffs alone.',
			'Confirm whether any removed routes, removed tables, or altered schema statements introduce upgrade steps.',
		],
		'manual_review_required' => [
			'summary',
			'breaking_changes',
			'ai_notes',
		],
		'generator' => [
			'name' => 'generate_release_manifest.php',
			'generated_at' => date('c'),
			'comparison_basis' => $previousVersion ? "Compared against release snapshot {$previousVersion}" : 'No previous release snapshot found',
		],
	];

	writeJson($config['manifest_file'], $manifest);

	$snapshotDir = $config['releases_dir'] . DS . $version;
	ensureDirectory($snapshotDir);

	writeJson($snapshotDir . DS . 'file-index.json', $currentIndex);
	writeJson($snapshotDir . DS . 'routes.json', $currentRoutes);
	writeJson($snapshotDir . DS . 'schema-signals.json', $currentSchema);

	writeJson($snapshotDir . DS . 'release-snapshot.json', [
		'version' => $version,
		'release_date' => $releaseDate,
		'file_index' => $currentIndex,
		'routes' => $currentRoutes,
		'schema' => $currentSchema,
	]);

	echo PHP_EOL;
	echo "Release manifest generated successfully." . PHP_EOL;
	echo "Version: {$version}" . PHP_EOL;
	echo "Manifest: {$config['manifest_file']}" . PHP_EOL;
	echo "Snapshot: {$snapshotDir}" . PHP_EOL;
	echo PHP_EOL;

	echo "Summary:" . PHP_EOL;
	echo "  Added files:   " . count($diff['added']) . PHP_EOL;
	echo "  Changed files: " . count($diff['changed']) . PHP_EOL;
	echo "  Removed files: " . count($diff['removed']) . PHP_EOL;
	echo "  Added routes:  " . count($routeDiff['added']) . PHP_EOL;
	echo "  Removed routes:" . count($routeDiff['removed']) . PHP_EOL;
	echo "  Added tables:  " . count($schemaDiff['tables_added']) . PHP_EOL;
	echo "  Removed tables:" . count($schemaDiff['tables_removed']) . PHP_EOL;
	echo "  Added ALTERs:  " . count($schemaDiff['alters_added']) . PHP_EOL;
	echo PHP_EOL;
}

/**
* Reads and validates the VERSION file.
*/
function readVersion(string $versionFile): string
{
	if (!is_file($versionFile)) {
		fail("VERSION file not found at {$versionFile}");
	}

	$version = trim((string) file_get_contents($versionFile));
	if ($version === '') {
		fail('VERSION file is empty.');
	}

	return $version;
}

/**
* Builds a file index for the project:
* [
*   "path/to/file.php" => [
*      "size" => 1234,
*      "mtime" => 1714972341,
*      "sha1" => "..."
*   ]
* ]
*/
function buildFileIndex(string $root, array $config): array
{
	$index = [];

	$directoryIterator = new RecursiveDirectoryIterator(
	$root,
	FilesystemIterator::SKIP_DOTS
	);

	$filter = new RecursiveCallbackFilterIterator(
	$directoryIterator,
	function (SplFileInfo $current, string $key, $iterator) use ($root, $config): bool {
		$pathname = $current->getPathname();
		$relative = normalizeRelativePath(relativePath($root, $pathname));

		foreach ($config['exclude_paths'] as $excluded) {
			$excluded = trim($excluded, '/\\');
			if ($relative === $excluded || str_starts_with($relative . '/', $excluded . '/')) {
				return false;
			}
		}

		if ($current->isFile()) {
			if (in_array($current->getFilename(), $config['exclude_filenames'], true)) {
				return false;
			}

			if (in_array($relative, $config['exclude_relative_files'], true)) {
				return false;
			}
		}

		return true;
	}
	);

	$iterator = new RecursiveIteratorIterator($filter);

/** @var SplFileInfo $file */
	foreach ($iterator as $file) {
		if (!$file->isFile()) {
			continue;
		}

		$absolutePath = $file->getPathname();
		$relativePath = normalizeRelativePath(relativePath($root, $absolutePath));

		$index[$relativePath] = [
			'size' => $file->getSize(),
			'mtime' => $file->getMTime(),
			'sha1' => sha1_file($absolutePath) ?: '',
		];
	}

	ksort($index);
	return $index;
}

/**
* Loads the most recent previous release snapshot, excluding the current version.
*/
function loadPreviousSnapshot(string $releasesDir, string $currentVersion): array
{
	if (!is_dir($releasesDir)) {
		return [null, []];
	}

	$entries = scandir($releasesDir);
	if ($entries === false) {
		return [null, []];
	}

	$versions = [];
	foreach ($entries as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$path = $releasesDir . DS . $entry;
		if (!is_dir($path)) {
			continue;
		}

		if ($entry === $currentVersion) {
			continue;
		}

		$versions[] = $entry;
	}

	if ($versions === []) {
		return [null, []];
	}

	usort($versions, 'version_compare');
	$previousVersion = end($versions);
	if ($previousVersion === false) {
		return [null, []];
	}

	$snapshotFile = $releasesDir . DS . $previousVersion . DS . 'release-snapshot.json';
	if (!is_file($snapshotFile)) {
		return [$previousVersion, []];
	}

	$json = file_get_contents($snapshotFile);
	$data = json_decode((string) $json, true);

	if (!is_array($data)) {
		return [$previousVersion, []];
	}

	return [$previousVersion, $data];
}

/**
* True when a relative path sits under one of the excluded directories.
*/
function isExcludedPath(string $relative, array $excludePaths): bool
{
	foreach ($excludePaths as $excluded) {
		$excluded = trim($excluded, '/\\');
		if ($relative === $excluded || str_starts_with($relative . '/', $excluded . '/')) {
			return true;
		}
	}
	return false;
}

/**
* Diffs current vs previous file index.
*/
function diffFileIndexes(array $previous, array $current): array
{
	$previousPaths = array_keys($previous);
	$currentPaths = array_keys($current);

	$added = array_values(array_diff($currentPaths, $previousPaths));
	$removed = array_values(array_diff($previousPaths, $currentPaths));

	$changed = [];
	foreach (array_intersect($previousPaths, $currentPaths) as $path) {
		$prevHash = $previous[$path]['sha1'] ?? null;
		$currHash = $current[$path]['sha1'] ?? null;

		if ($prevHash !== $currHash) {
			$changed[] = $path;
		}
	}

	sort($added);
	sort($changed);
	sort($removed);

	return [
		'added' => $added,
		'changed' => $changed,
		'removed' => $removed,
	];
}

/**
* Extracts route definitions from bootstrap.php using a pragmatic regex.
*
* Expected pattern:
* $router->add(METHOD, PATTERN, HANDLER, [MIDDLEWARE]);
*
* Output format:
* [
*   "GET /login => AuthController@showLogin [json]" ,
*   ...
* ]
*/
function extractRoutes(string $bootstrapFile): array
{
	if (!is_file($bootstrapFile)) {
		return [];
	}

	$content = (string) file_get_contents($bootstrapFile);
	if ($content === '') {
		return [];
	}

	$routes = [];

	$pattern = '/\$router->add\(\s*([^\s,]+)\s*,\s*([^\s,]+)\s*,\s*([^\n,]+?)\s*,\s*(\[[^\)]*\])\s*\)/m';
	if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$method = normalizePhpArg($match[1] ?? '');
			$routePattern = normalizePhpArg($match[2] ?? '');
			$handler = normalizePhpArg($match[3] ?? '');
			$middleware = trim($match[4] ?? '[]');

			$routes[] = trim(sprintf(
			'%s %s => %s %s',
			$method,
			$routePattern,
			$handler,
			normalizeWhitespace($middleware)
			));
		}
	}

	$routes = array_values(array_unique($routes));
	sort($routes);

	return $routes;
}

/**
* Extracts basic schema signals from sql/schema.core.sql.
*
* Output:
* [
*   'tables' => [...],
*   'alter_statements' => [...]
* ]
*/
function extractSchemaSignals(string $schemaFile): array
{
	if (!is_file($schemaFile)) {
		return defaultSchemaSignals();
	}

	$content = (string) file_get_contents($schemaFile);
	if ($content === '') {
		return defaultSchemaSignals();
	}

	$tables = [];
	if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $content, $matches)) {
		$tables = $matches[1];
	}

	$alters = [];
	if (preg_match_all('/ALTER\s+TABLE\s+.+?;/is', $content, $matches)) {
		foreach ($matches[0] as $stmt) {
			$alters[] = normalizeWhitespace(trim($stmt));
		}
	}

	$tables = array_values(array_unique($tables));
	sort($tables);

	$alters = array_values(array_unique($alters));
	sort($alters);

	return [
		'tables' => $tables,
		'alter_statements' => $alters,
	];
}

/**
* Diffs routes.
*/
function diffRoutes(array $previous, array $current): array
{
	$added = array_values(array_diff($current, $previous));
	$removed = array_values(array_diff($previous, $current));

	sort($added);
	sort($removed);

	return [
		'added' => $added,
		'removed' => $removed,
	];
}

/**
* Diffs schema signals.
*/
function diffSchemaSignals(array $previous, array $current): array
{
	$previousTables = $previous['tables'] ?? [];
	$currentTables = $current['tables'] ?? [];

	$previousAlters = $previous['alter_statements'] ?? [];
	$currentAlters = $current['alter_statements'] ?? [];

	$tablesAdded = array_values(array_diff($currentTables, $previousTables));
	$tablesRemoved = array_values(array_diff($previousTables, $currentTables));
	$altersAdded = array_values(array_diff($currentAlters, $previousAlters));

	sort($tablesAdded);
	sort($tablesRemoved);
	sort($altersAdded);

	return [
		'tables_added' => $tablesAdded,
		'tables_removed' => $tablesRemoved,
		'alters_added' => $altersAdded,
	];
}

/**
* Stub summary for manual refinement.
*/
function buildSummaryStub(array $diff, array $routeDiff, array $schemaDiff): string
{
	$parts = [];

	if (count($diff['added']) > 0) {
		$parts[] = count($diff['added']) . ' files added';
	}

	if (count($diff['changed']) > 0) {
		$parts[] = count($diff['changed']) . ' files changed';
	}

	if (count($diff['removed']) > 0) {
		$parts[] = count($diff['removed']) . ' files removed';
	}

	if (count($routeDiff['added']) > 0) {
		$parts[] = count($routeDiff['added']) . ' routes added';
	}

	if (count($routeDiff['removed']) > 0) {
		$parts[] = count($routeDiff['removed']) . ' routes removed';
	}

	if (count($schemaDiff['tables_added']) > 0) {
		$parts[] = count($schemaDiff['tables_added']) . ' tables added';
	}

	if (count($schemaDiff['tables_removed']) > 0) {
		$parts[] = count($schemaDiff['tables_removed']) . ' tables removed';
	}

	if (count($schemaDiff['alters_added']) > 0) {
		$parts[] = count($schemaDiff['alters_added']) . ' ALTER statements added';
	}

	if ($parts === []) {
		return 'No material changes detected compared with the previous release snapshot.';
	}

	return 'Draft summary: ' . implode('; ', $parts) . '. Review and rewrite before release.';
}

/**
* Writes pretty JSON.
*/
function writeJson(string $path, array $data): void
{
	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		fail("Failed to encode JSON for {$path}");
	}

	$result = file_put_contents($path, $json . PHP_EOL);
	if ($result === false) {
		fail("Failed to write file {$path}");
	}
}

/**
* Ensures directory exists.
*/
function ensureDirectory(string $path): void
{
	if (!is_dir($path)) {
		if (!mkdir($path, 0775, true) && !is_dir($path)) {
			fail("Failed to create directory {$path}");
		}
	}
}

/**
* Relative path from root.
*/
function relativePath(string $root, string $path): string
{
	$root = rtrim(str_replace('\\', '/', $root), '/');
	$path = str_replace('\\', '/', $path);

	if (str_starts_with($path, $root . '/')) {
		return substr($path, strlen($root) + 1);
	}

	return $path;
}

/**
* Normalizes a relative path.
*/
function normalizeRelativePath(string $path): string
{
	return trim(str_replace('\\', '/', $path), '/');
}

/**
* Normalizes PHP route args from quoted strings or constants.
*/
function normalizePhpArg(string $value): string
{
	$value = trim($value);
	$value = rtrim($value, ',');

	if (
	(str_starts_with($value, "'") && str_ends_with($value, "'")) ||
	(str_starts_with($value, '"') && str_ends_with($value, '"'))
	) {
		$value = substr($value, 1, -1);
	}

	return normalizeWhitespace($value);
}

/**
* Reduces repeated whitespace.
*/
function normalizeWhitespace(string $value): string
{
	return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
}

/**
* Default empty schema signals.
*/
function defaultSchemaSignals(): array
{
	return [
		'tables' => [],
		'alter_statements' => [],
	];
}

/**
* Ends execution with a message.
*/
function fail(string $message): void
{
	fwrite(STDERR, '[ERROR] ' . $message . PHP_EOL);
	exit(1);
}