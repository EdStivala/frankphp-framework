<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

	public static function connect(array $db): PDO
	{
		if (self::$pdo === null) {
			// 1. Initial connection to the server (without selecting a DB)
			$database = $db['database'];
			$pdo = new PDO($db['dsn'], $db['user'], $db['pass']);

			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			
			// 2. Force UTC for this MySQL session immediately after connecting.
			//    This must happen before any queries — including CREATE DATABASE —
			//    so that CURRENT_TIMESTAMP and NOW() always return UTC regardless
			//    of the server's system timezone setting.
			$pdo->exec("SET time_zone = '+00:00'");

			// 3. Create database if it doesn't exist
			$pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
			$pdo->exec("USE `$database`;");

			self::$pdo = $pdo;

			// 4. Framework schema — guarded on 'users', framework's own
			//    sentinel table. Seed data (Tenant 1, owner@tenant1.com /
			//    user@tenant1.com — see docs.n3wmedia.com's install guide)
			//    lives inside schema.core.sql itself and runs as part of
			//    this same import. Do not seed anywhere else — a second,
			//    different seed set previously ran alongside this one.
			self::runSchemaFileIfMissingTable(__DIR__ . '/../sql/schema.core.sql', 'users');
		}
		return self::$pdo;
	}

    public static function getPdo(): PDO
    {
        if (self::$pdo === null) throw new \RuntimeException('Database not initialized. Call Database::connect(path) first.');
        return self::$pdo;
    }

    /**
     * Run $fn inside a single transaction on the shared connection.
     * Commits on return, rolls back and rethrows on any Throwable.
     * Introduced in v2.1.0 for tenant-on-signup (tenant + owner user +
     * token update must succeed or fail together).
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::getPdo();
        $pdo->beginTransaction();

        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Runs a schema file exactly once, guarded by the absence of a
     * sentinel table — the framework's own reusable "create schema on
     * first boot" primitive. Framework bootstrap uses this for
     * schema.core.sql/'users'; app/bootstrap.php uses it identically for
     * its own schema.app.sql and an app-chosen sentinel table. This is a
     * first-boot bootstrapper, not an incremental migration system — see
     * codebase.md for the policy on evolving an already-created schema.
     */
    public static function runSchemaFileIfMissingTable(string $path, string $sentinelTable): void
    {
        $pdo = self::getPdo();

        $check = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($sentinelTable))->fetch();
        if ($check !== false) {
            return;
        }

        $schema = file_get_contents($path);

        // Enable multi-statement support for the schema import, then
        // re-disable it immediately after for normal app operation.
        $pdo->setAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS, true);
        $pdo->exec($schema);
        $pdo->setAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS, false);
    }
}
