<?php

declare(strict_types=1);

/**
* Build Release Script
*
* Suggested location:
*   /tools/build_release.php
*
* Run from project root:
*   php tools/build_release.php
*
* What it does:
* - Reads version from /VERSION
* - Creates /dist/frankphp-{version}/
* - Copies release-safe files into the dist folder
* - Excludes local-only/internal files like .env, releases/, tools/, .git, etc.
* - Creates /dist/frankphp-{version}.zip
*
* Notes:
* - This script uses an exclusion-based approach with a few required files checked explicitly.
* - Adjust PRODUCT_SLUG to match your product name.
*/

const DS = DIRECTORY_SEPARATOR;
const PRODUCT_SLUG = 'frankphp';

$root = realpath(__DIR__ . '/..');
if ($root === false) {
	fail('Could not resolve project root.');
}

$config = [
	'root' => $root,
	'version_file' => $root . DS . 'VERSION',
	'dist_dir' => $root . DS . 'dist',

	'required_files' => [
		'VERSION',
		'CHANGELOG.md',
		'codebase.md',
		'RELEASE_MANIFEST.json',
		'bootstrap.php',
		'Core',
		'Controllers',
		'Middleware',
		'Models',
		'Services',
		'sql',
	],

	'exclude_paths' => [
		'.git',
		'.github',
		'.idea',
		'.vscode',
		'node_modules',
		'vendor',
		'dist',
		'releases',
		'tools',
		'tests',
		'Versions',
		'storage/logs',
		'storage/cache',
	],

	'exclude_files' => [
		'.env',
		'.env.local',
		'.DS_Store',
		'Thumbs.db',
		'phpunit.xml',
		'phpunit.xml.dist',
	],

	'exclude_extensions' => [
		'log',
		'tmp',
		'clpprj',
		'csprj',
		'swp',
	],
];

main($config);

function main(array $config): void
{
	ensureDirectory($config['dist_dir']);

	$version = readVersion($config['version_file']);
	$packageDirName = PRODUCT_SLUG . '-' . $version;
	$packageDir = $config['dist_dir'] . DS . $packageDirName;
	$zipPath = $config['dist_dir'] . DS . $packageDirName . '.zip';

	assertRequiredPathsExist($config['root'], $config['required_files']);

	if (is_dir($packageDir)) {
		deleteDirectory($packageDir);
	}

	if (is_file($zipPath)) {
		if (!unlink($zipPath)) {
			fail("Failed to remove existing zip: {$zipPath}");
		}
	}

	ensureDirectory($packageDir);

	echo PHP_EOL;
	echo "Building release {$version}" . PHP_EOL;
	echo "Target folder: {$packageDir}" . PHP_EOL;
	echo "Target zip:    {$zipPath}" . PHP_EOL;
	echo PHP_EOL;

	$copiedCount = copyReleaseFiles($config['root'], $packageDir, $config);

	createZipFromDirectory($packageDir, $zipPath);

	echo PHP_EOL;
	echo "Release build complete." . PHP_EOL;
	echo "Files copied: {$copiedCount}" . PHP_EOL;
	echo "Folder: {$packageDir}" . PHP_EOL;
	echo "Zip: {$zipPath}" . PHP_EOL;
	echo PHP_EOL;
}

/**
* Reads and validates VERSION.
*/
function readVersion(string $versionFile): string
{
	if (!is_file($versionFile)) {
		fail("VERSION file not found at {$versionFile}");
	}

	$raw = (string) file_get_contents($versionFile);
	$version = trim($raw);

	if ($version === '') {
		fail('VERSION file is empty.');
	}

	if (preg_match('/^\{\\\\rtf/i', $version)) {
		fail('VERSION file appears to be RTF, not plain text.');
	}

	if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
		fail("VERSION file must contain only a semantic version like 1.1.0. Found: {$version}");
	}

	return $version;
}

/**
* Ensure key files/folders exist before building.
*/
function assertRequiredPathsExist(string $root, array $requiredPaths): void
{
	foreach ($requiredPaths as $relativePath) {
		$absolutePath = $root . DS . str_replace(['/', '\\'], DS, $relativePath);
		if (!file_exists($absolutePath)) {
			fail("Required release path is missing: {$relativePath}");
		}
	}
}

/**
* Copies files from root to package dir using exclusion rules.
*/
function copyReleaseFiles(string $root, string $packageDir, array $config): int
{
	$count = 0;

	$directoryIterator = new RecursiveDirectoryIterator(
	$root,
	FilesystemIterator::SKIP_DOTS
	);

	$filter = new RecursiveCallbackFilterIterator(
	$directoryIterator,
	function (SplFileInfo $current, string $key, $iterator) use ($root, $config): bool {
		$relative = normalizeRelativePath(relativePath($root, $current->getPathname()));
		$basename = $current->getFilename();

		foreach ($config['exclude_paths'] as $excluded) {
			$excluded = trim($excluded, '/\\');
			if ($relative === $excluded || str_starts_with($relative . '/', $excluded . '/')) {
				return false;
			}
		}

		if (in_array($basename, $config['exclude_files'], true)) {
			return false;
		}

		if ($current->isFile()) {
			$extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
			if ($extension !== '' && in_array($extension, $config['exclude_extensions'], true)) {
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

		$sourcePath = $file->getPathname();
		$relative = normalizeRelativePath(relativePath($root, $sourcePath));
		$destinationPath = $packageDir . DS . str_replace('/', DS, $relative);
		$destinationDir = dirname($destinationPath);

		ensureDirectory($destinationDir);

		if (!copy($sourcePath, $destinationPath)) {
			fail("Failed to copy file: {$relative}");
		}

		$count++;
	}

	return $count;
}

/**
* Creates a zip archive from a directory.
*/
function createZipFromDirectory(string $sourceDir, string $zipPath): void
{
	if (!class_exists('ZipArchive')) {
		fail('ZipArchive is not available in this PHP installation.');
	}

	$zip = new ZipArchive();
	$result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

	if ($result !== true) {
		fail("Failed to create zip archive: {$zipPath}");
	}

	$sourceDir = realpath($sourceDir);
	if ($sourceDir === false) {
		$zip->close();
		fail("Could not resolve source directory: {$sourceDir}");
	}

	$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::SELF_FIRST
	);

/** @var SplFileInfo $item */
	foreach ($iterator as $item) {
		$absolutePath = $item->getPathname();
		$relativePath = normalizeRelativePath(relativePath($sourceDir, $absolutePath));

		if ($item->isDir()) {
			$zip->addEmptyDir($relativePath);
			continue;
		}

		if (!$zip->addFile($absolutePath, $relativePath)) {
			$zip->close();
			fail("Failed to add file to zip: {$relativePath}");
		}
	}

	$zip->close();
}

/**
* Deletes a directory recursively.
*/
function deleteDirectory(string $path): void
{
	if (!is_dir($path)) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::CHILD_FIRST
	);

/** @var SplFileInfo $item */
	foreach ($iterator as $item) {
		$pathname = $item->getPathname();

		if ($item->isDir()) {
			if (!rmdir($pathname)) {
				fail("Failed to remove directory: {$pathname}");
			}
		} else {
			if (!unlink($pathname)) {
				fail("Failed to remove file: {$pathname}");
			}
		}
	}

	if (!rmdir($path)) {
		fail("Failed to remove directory: {$path}");
	}
}

/**
* Ensures a directory exists.
*/
function ensureDirectory(string $path): void
{
	if (is_dir($path)) {
		return;
	}

	if (!mkdir($path, 0775, true) && !is_dir($path)) {
		fail("Failed to create directory {$path}");
	}
}

/**
* Converts absolute path to relative path from root.
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
* Normalizes path separators.
*/
function normalizeRelativePath(string $path): string
{
	return trim(str_replace('\\', '/', $path), '/');
}

/**
* Prints error and exits.
*/
function fail(string $message): void
{
	fwrite(STDERR, '[ERROR] ' . $message . PHP_EOL);
	exit(1);
}