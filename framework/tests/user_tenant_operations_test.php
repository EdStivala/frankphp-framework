<?php

declare(strict_types=1);

/**
* User / Tenant Operations Test (v3.0.0)
*
* Run before every release:
*   /usr/local/bin/php framework/tests/user_tenant_operations_test.php
*
* What it checks: UserService::deleteUser(), UserService::changeRole(),
* UserService::saveSettings() (name, timezone, email) and
* TenantService::deleteTenant() against a real MySQL database.
*
* Database: ONLY a scratch database named frank_v3_test on the MySQL server
* configured in app/.env (host / port / user / password are read in code and
* never printed). The test creates it, loads framework/sql/schema.core.sql
* through the normal first-boot path (Database::connect), and drops it at the
* end. The app's real database (DB_NAME) is never opened.
*
* Exits non-zero on any failure.
*/

const SCRATCH_DB = 'frank_v3_test';

// Database.php still uses PDO::MYSQL_ATTR_MULTI_STATEMENTS, deprecated in PHP 8.5. Silence that
// notice here: its stack trace would print the connection arguments.
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

define('APP_BASE_DIR', dirname(__DIR__, 2));

spl_autoload_register(function (string $class): void {
	$roots = ['Frank\\' => APP_BASE_DIR . '/framework', 'App\\' => APP_BASE_DIR . '/app'];
	foreach ($roots as $prefix => $root) {
		if (strncmp($prefix, $class, strlen($prefix)) === 0) {
			$file = $root . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file)) {
				require $file;
			}
			return;
		}
	}
});

use Frank\Core\Database;
use Frank\Core\Env;
use Frank\Models\Tenant;
use Frank\Models\User;
use Frank\Services\TenantService;
use Frank\Services\UserService;

$failures = 0;

// ---------------------------------------------------------------------
// Scratch database
// ---------------------------------------------------------------------
Env::load(APP_BASE_DIR . '/app/.env');

$server = sprintf(
	'mysql:host=%s;port=%s;charset=utf8mb4',
	Env::require('DB_HOST'),
	Env::get('DB_PORT', '3306')
);

try {
	$admin = new PDO($server, Env::require('DB_USER'), Env::require('DB_PASS'), [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	]);

	dropScratchDatabase($admin);
	$admin->exec('CREATE DATABASE `' . SCRATCH_DB . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (Throwable $e) {
	echo 'Could not prepare the scratch database (' . get_class($e) . ').' . PHP_EOL;
	exit(2);
}

try {
	Database::connect([
		'database' => SCRATCH_DB,
		'dsn'      => $server . ';dbname=' . SCRATCH_DB,
		'user'     => Env::require('DB_USER'),
		'pass'     => Env::require('DB_PASS'),
	]);
	$pdo = Database::getPdo();

	$userModel  = new User();
	$userSvc    = new UserService($userModel);
	$tenantSvc  = new TenantService(new Tenant());

	$count = static fn (string $table, string $where = '1=1'): int =>
		(int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();

	$mkUser = static function (int $tenantId, string $email, string $role, string $name = 'Person'): int {
		$u = new User();
		$u->fill(['tenant_id' => $tenantId, 'email' => $email, 'name' => $name, 'role' => $role, 'created_at' => gmdate('Y-m-d H:i:s')]);
		$u->save();
		return (int) User::where($tenantId, 'email', $email)[0]->id;
	};

	// Tenant A: owner, admin, two plain users and a platform user. Tenant B: owner + user.
	$tenantA = $tenantSvc->create('Acme Corp', 'acme-corp');
	$tenantB = $tenantSvc->create('Beta Ltd', 'beta-ltd');
	$aOwner  = $mkUser($tenantA, 'owner@a.test', 'owner', 'A Owner');
	$aAdmin  = $mkUser($tenantA, 'admin@a.test', 'admin', 'A Admin');
	$aUser1  = $mkUser($tenantA, 'user1@a.test', 'user', 'A User One');
	$aUser2  = $mkUser($tenantA, 'user2@a.test', 'user', 'A User Two');
	$aPlat   = $mkUser($tenantA, 'platform@a.test', 'platform', 'A Platform');
	$bOwner  = $mkUser($tenantB, 'owner@b.test', 'owner', 'B Owner');
	$bUser   = $mkUser($tenantB, 'user@b.test', 'user', 'B User');

	$roleOf = static fn (int $id): ?string => ($r = $userModel->findById($id)) ? $r['role'] : null;
	$exists = static fn (int $id): bool => $userModel->findById($id) !== null;

	// -----------------------------------------------------------------
	echo 'UserService::deleteUser' . PHP_EOL;
	// -----------------------------------------------------------------
	$r = $userSvc->deleteUser($tenantA, 999999);
	$failures += check('refuses a user that does not exist', !$r->success && $r->errorType === 'not_found');

	$r = $userSvc->deleteUser($tenantB, $aUser1);
	$failures += check('refuses a user in another tenant (tenant A user, tenant B id)', !$r->success && $r->errorType === 'not_found' && $exists($aUser1));

	$r = $userSvc->deleteUser($tenantA, $bUser);
	$failures += check('refuses a user in another tenant (tenant B user, tenant A id)', !$r->success && $r->errorType === 'not_found' && $exists($bUser));

	$r = $userSvc->deleteUser($tenantA, $aOwner);
	$failures += check('refuses the owner with owner_requires_tenant_delete', !$r->success && $r->errorType === 'owner_requires_tenant_delete' && $exists($aOwner));

	$r = $userSvc->deleteUser($tenantA, $aUser1);
	$failures += check('deletes a plain user in the tenant', $r->success && !$exists($aUser1));

	$r = $userSvc->deleteUser($tenantA, $aAdmin);
	$failures += check('deletes an admin in the tenant', $r->success && !$exists($aAdmin));

	$failures += check('other users are untouched', $exists($aOwner) && $exists($aUser2) && $exists($bOwner) && $exists($bUser));

	// -----------------------------------------------------------------
	echo PHP_EOL . 'UserService::changeRole' . PHP_EOL;
	// -----------------------------------------------------------------
	$r = $userSvc->changeRole($tenantA, 999999, 'admin');
	$failures += check('refuses a user that does not exist', !$r->success && $r->errorType === 'not_found');

	$r = $userSvc->changeRole($tenantB, $aUser2, 'admin');
	$failures += check('refuses a user in another tenant', !$r->success && $r->errorType === 'not_found' && $roleOf($aUser2) === 'user');

	$r = $userSvc->changeRole($tenantA, $aOwner, 'admin');
	$failures += check('refuses when the target is the owner', !$r->success && $r->errorType === 'role_protected' && $roleOf($aOwner) === 'owner');

	$r = $userSvc->changeRole($tenantA, $aPlat, 'user');
	$failures += check('refuses when the target is platform', !$r->success && $r->errorType === 'role_protected' && $roleOf($aPlat) === 'platform');

	$r = $userSvc->changeRole($tenantA, $aUser2, 'owner');
	$failures += check('refuses a change to owner', !$r->success && $r->errorType === 'role_not_assignable' && $roleOf($aUser2) === 'user');

	$r = $userSvc->changeRole($tenantA, $aUser2, 'platform');
	$failures += check('refuses a change to platform', !$r->success && $r->errorType === 'role_not_assignable' && $roleOf($aUser2) === 'user');

	$r = $userSvc->changeRole($tenantA, $aUser2, ' Owner ');
	$failures += check('refuses owner even with spaces or different case', !$r->success && $roleOf($aUser2) === 'user');

	$r = $userSvc->changeRole($tenantA, $aUser2, '');
	$failures += check('refuses an empty role', !$r->success && $r->isValidationError() && $roleOf($aUser2) === 'user');

	$r = $userSvc->changeRole($tenantA, $aUser2, str_repeat('x', 51));
	$failures += check('refuses a role longer than 50 characters', !$r->success && $r->isValidationError() && $roleOf($aUser2) === 'user');

	$r = $userSvc->changeRole($tenantA, $aUser2, str_repeat('x', 50));
	$failures += check('accepts a role of exactly 50 characters', $r->success && $roleOf($aUser2) === str_repeat('x', 50));

	$r = $userSvc->changeRole($tenantA, $aUser2, 'admin');
	$failures += check('changes user to admin', $r->success && $roleOf($aUser2) === 'admin');

	$r = $userSvc->changeRole($tenantA, $aUser2, 'user');
	$failures += check('changes admin back to user', $r->success && $roleOf($aUser2) === 'user');

	$r = $userSvc->changeRole($tenantA, $aUser2, 'billing-contact');
	$failures += check('accepts any role name (no list is checked)', $r->success && $roleOf($aUser2) === 'billing-contact');
	$userSvc->changeRole($tenantA, $aUser2, 'user');

	$failures += check('owner and platform roles are unchanged throughout', $roleOf($aOwner) === 'owner' && $roleOf($aPlat) === 'platform' && $roleOf($bOwner) === 'owner');

	// -----------------------------------------------------------------
	echo PHP_EOL . 'UserService::saveSettings' . PHP_EOL;
	// -----------------------------------------------------------------
	$ok = $userSvc->saveSettings($aUser2, $tenantA, ['name' => '  New Name  ']);
	$row = $userModel->findById($aUser2);
	$failures += check('a valid name is saved, trimmed', $ok === true && $row['name'] === 'New Name');

	$ok = $userSvc->saveSettings($aUser2, $tenantA, ['name' => '   ']);
	$row = $userModel->findById($aUser2);
	$failures += check('an empty (whitespace) name is rejected and nothing changes', $ok === false && $row['name'] === 'New Name');

	$ok = $userSvc->saveSettings($aUser2, $tenantA, ['name' => '']);
	$failures += check('an empty-string name is rejected', $ok === false && $userModel->findById($aUser2)['name'] === 'New Name');

	$ok = $userSvc->saveSettings($aUser2, $tenantA, ['name' => str_repeat('n', 256), 'timezone' => 'Europe/London']);
	$row = $userModel->findById($aUser2);
	$failures += check('a name over 255 characters is rejected and the whole save is refused', $ok === false && $row['name'] === 'New Name' && $row['timezone'] === null);

	$ok = $userSvc->saveSettings($aUser2, $tenantA, ['name' => str_repeat('n', 255)]);
	$failures += check('a name of exactly 255 characters is accepted', $ok === true && mb_strlen($userModel->findById($aUser2)['name']) === 255);
	$userSvc->saveSettings($aUser2, $tenantA, ['name' => 'New Name']);

	$ok = $userSvc->saveSettings($aUser2, $tenantA, ['timezone' => 'Europe/London']);
	$failures += check('timezone still saves without a name', $ok === true && $userModel->findById($aUser2)['timezone'] === 'Europe/London');

	$ok = $userSvc->saveSettings($aUser2, $tenantA, ['timezone' => '']);
	$failures += check('empty timezone still reverts to NULL', $ok === true && $userModel->findById($aUser2)['timezone'] === null);

	$before = $userModel->findById($aUser2);
	$userSvc->saveSettings($aUser2, $tenantA, ['name' => 'Another', 'email' => 'hacked@a.test', 'role' => 'owner', 'tenant_id' => $tenantB]);
	$after = $userModel->findById($aUser2);
	$failures += check('email cannot be changed through saveSettings', $after['email'] === $before['email'] && $userModel->findByEmail('hacked@a.test') === null);
	$failures += check('role and tenant cannot be changed through saveSettings either', $after['role'] === 'user' && (int) $after['tenant_id'] === $tenantA && $after['name'] === 'Another');

	// -----------------------------------------------------------------
	echo PHP_EOL . 'TenantService::deleteTenant' . PHP_EOL;
	// -----------------------------------------------------------------
	$ownerA = $userModel->findById($aOwner);
	$userA  = $userModel->findById($aUser2);
	$ownerB = $userModel->findById($bOwner);
	$platA  = $userModel->findById($aPlat);
	$tenantsBefore = $count('tenants');

	$r = $tenantSvc->deleteTenant($userA, $tenantA);
	$failures += check('refuses a user who is not the owner', !$r->success && $r->errorType === 'forbidden' && $count('tenants') === $tenantsBefore);

	$r = $tenantSvc->deleteTenant($platA, $tenantA);
	$failures += check('refuses a platform user', !$r->success && $r->errorType === 'forbidden' && $count('tenants') === $tenantsBefore);

	$r = $tenantSvc->deleteTenant(['role' => 'admin', 'tenant_id' => $tenantA], $tenantA);
	$failures += check('refuses an admin', !$r->success && $count('tenants') === $tenantsBefore);

	$r = $tenantSvc->deleteTenant($ownerB, $tenantA);
	$failures += check("refuses another tenant's owner", !$r->success && $r->errorType === 'forbidden' && $count('tenants') === $tenantsBefore && $exists($aOwner));

	$usersInBBefore = $count('users', "tenant_id = {$tenantB}");
	$r = $tenantSvc->deleteTenant($ownerA, $tenantA);
	$failures += check('the owner deletes their own tenant', $r->success && $count('tenants', "id = {$tenantA}") === 0);
	$failures += check('all of that tenant\'s users are gone', $count('users', "tenant_id = {$tenantA}") === 0
		&& !$exists($aOwner) && !$exists($aUser2) && !$exists($aPlat));
	$failures += check('the other tenant and its users are untouched', $count('tenants', "id = {$tenantB}") === 1
		&& $count('users', "tenant_id = {$tenantB}") === $usersInBBefore && $exists($bOwner) && $exists($bUser));

	$r = $tenantSvc->deleteTenant($ownerA, $tenantA);
	$failures += check('deleting an already-deleted tenant fails cleanly', !$r->success);
} catch (Throwable $e) {
	echo 'FAIL  unexpected ' . get_class($e) . ' (details withheld)' . PHP_EOL;
	$failures++;
} finally {
	dropScratchDatabase($admin);
}

echo PHP_EOL . ($failures === 0 ? 'All user/tenant operation checks passed.' : "{$failures} check(s) failed.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);

function check(string $label, bool $passed): int
{
	echo '  ' . ($passed ? 'PASS  ' : 'FAIL  ') . $label . PHP_EOL;
	return $passed ? 0 : 1;
}

/**
* Drops the scratch database — and only the scratch database.
*/
function dropScratchDatabase(PDO $admin): void
{
	if (SCRATCH_DB !== 'frank_v3_test') {
		throw new LogicException('Refusing to drop anything but frank_v3_test.');
	}
	$admin->exec('DROP DATABASE IF EXISTS `frank_v3_test`');
}
