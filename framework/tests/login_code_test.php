<?php

declare(strict_types=1);

/**
* Login Code Test (v3.0.0)
*
* Run before every release:
*   /usr/local/bin/php framework/tests/login_code_test.php
*
* What it checks: LoginCodeService end to end against a real MySQL database,
* with a stub in place of EmailService that captures the emailed code (no real
* email is ever sent).
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
use Frank\Models\User;
use Frank\Services\CreateTenantForSignup;
use Frank\Services\Email\EmailResult;
use Frank\Services\Email\EmailService;
use Frank\Services\LoginCodeService;
use Frank\Services\TenantService;

/**
* Stand-in for EmailService: remembers the last code "sent" to each address
* and every platform-owner alert. Never touches SMTP.
*/
class CapturingEmailService extends EmailService
{
	/** @var array<string, string> */
	public array $codes = [];
	public int $loginCodeEmails = 0;
	/** @var string[] */
	public array $alerts = [];

	public function __construct()
	{
		// Deliberately not calling parent::__construct(): no SMTP settings needed.
	}

	public function sendLoginCode(string $email, string $code, int $expiryMinutes): EmailResult
	{
		$this->codes[$email] = $code;
		$this->loginCodeEmails++;
		return EmailResult::success();
	}

	public function sendPlatformOwnerSignupAlert(string $triggeringEmail, string $event): EmailResult
	{
		$this->alerts[] = $triggeringEmail . ' | ' . $event;
		return EmailResult::success();
	}
}

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
$admin = new PDO($server, Env::require('DB_USER'), Env::require('DB_PASS'), [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

dropScratchDatabase($admin);
$admin->exec('CREATE DATABASE `' . SCRATCH_DB . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
	// Normal first-boot path: connect, then schema.core.sql runs because 'users' is missing.
	Database::connect([
		'database' => SCRATCH_DB,
		'dsn'      => $server . ';dbname=' . SCRATCH_DB,
		'user'     => Env::require('DB_USER'),
		'pass'     => Env::require('DB_PASS'),
	]);
	$pdo = Database::getPdo();

	$email      = new CapturingEmailService();
	$userModel  = new User();
	$resolver   = new CreateTenantForSignup(new TenantService());
	$service    = new LoginCodeService($userModel, $email, $resolver);
	$termsSvc   = new LoginCodeService($userModel, $email, $resolver, requireTerms: true, termsVersion: 'test-1');

	$count = static fn (string $table, string $where = '1=1'): int =>
		(int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
	$wrongCodeFor = static fn (string $code): string => $code === '000000' ? '111111' : '000000';

	// -----------------------------------------------------------------
	echo 'Schema' . PHP_EOL;
	// -----------------------------------------------------------------
	$failures += check('fresh install starts empty: no tenants', $count('tenants') === 0);
	$failures += check('fresh install starts empty: no users', $count('users') === 0);
	$failures += check('login_codes table exists', tableExists($pdo, 'login_codes'));
	$failures += check('password_reset_tokens table is gone', !tableExists($pdo, 'password_reset_tokens'));
	$failures += check('signup_tokens table is gone', !tableExists($pdo, 'signup_tokens'));
	$failures += check('users.password_hash column is gone', !columnExists($pdo, 'users', 'password_hash'));

	// -----------------------------------------------------------------
	echo PHP_EOL . 'Unknown email' . PHP_EOL;
	// -----------------------------------------------------------------
	$r = $service->requestCode('New.Person@acme-corp.com', '198.51.100.1', 'test-agent');
	$failures += check('request succeeds and a code is emailed', $r->success && isset($email->codes['new.person@acme-corp.com']));
	$failures += check('email is normalised to lowercase', $r->get('email') === 'new.person@acme-corp.com');
	$failures += check('no tenant or user exists before the code is verified', $count('tenants') === 0 && $count('users') === 0);
	$failures += check('platform-owner alert is sent when an unknown address requests a code', count($email->alerts) === 1);

	$r = $service->verifyCode('new.person@acme-corp.com', $email->codes['new.person@acme-corp.com']);
	$failures += check('correct code logs in', $r->success && $r->get('status') === 'logged_in');
	$failures += check('a tenant and an owner user were created', $count('tenants') === 1 && $count('users', "role = 'owner'") === 1);
	$newUserId = (int) $r->get('user_id');
	$tenantId  = (int) $r->get('tenant_id');
	$row = $userModel->findByEmail('new.person@acme-corp.com');
	$failures += check('user is the owner of the new tenant', $row !== null && (int) $row['id'] === $newUserId && (int) $row['tenant_id'] === $tenantId && $row['role'] === 'owner');
	$failures += check('name derived from the email', $row !== null && $row['name'] === 'New Person');
	$failures += check('terms columns stay NULL when require_terms is off', $row !== null && $row['terms_accepted_at'] === null && $row['terms_version'] === null);
	$failures += check('a second platform-owner alert is sent when the account is created', count($email->alerts) === 2);

	// -----------------------------------------------------------------
	echo PHP_EOL . 'Known email' . PHP_EOL;
	// -----------------------------------------------------------------
	$tenantsBefore = $count('tenants');
	$usersBefore   = $count('users');
	$alertsBefore  = count($email->alerts);
	$r = $service->requestCode('new.person@acme-corp.com', '198.51.100.2', 'test-agent');
	$failures += check('request succeeds and a code is emailed', $r->success);
	$r = $service->verifyCode('new.person@acme-corp.com', $email->codes['new.person@acme-corp.com']);
	$failures += check('correct code returns the same user', $r->success && (int) $r->get('user_id') === $newUserId && (int) $r->get('tenant_id') === $tenantId);
	$failures += check('no second tenant and no second user', $count('tenants') === $tenantsBefore && $count('users') === $usersBefore);
	$failures += check('no new-account alert for an existing user', count($email->alerts) === $alertsBefore);

	// -----------------------------------------------------------------
	echo PHP_EOL . 'Known and unknown behave identically before verification' . PHP_EOL;
	// -----------------------------------------------------------------
	$known   = $service->requestCode('new.person@acme-corp.com', '198.51.100.3', '');
	$unknown = $service->requestCode('someone.else@example.org', '198.51.100.3', '');
	$failures += check('same success, message and data keys', $known->success === $unknown->success
		&& $known->message === $unknown->message
		&& array_keys($known->data) === array_keys($unknown->data));
	$failures += check('a code was emailed to both', isset($email->codes['someone.else@example.org']) && isset($email->codes['new.person@acme-corp.com']));
	$knownWrong   = $service->verifyCode('new.person@acme-corp.com', $wrongCodeFor($email->codes['new.person@acme-corp.com']));
	$unknownWrong = $service->verifyCode('someone.else@example.org', $wrongCodeFor($email->codes['someone.else@example.org']));
	$failures += check('same wrong-code failure message', !$knownWrong->success && $knownWrong->message === $unknownWrong->message);

	// -----------------------------------------------------------------
	echo PHP_EOL . 'Wrong codes' . PHP_EOL;
	// -----------------------------------------------------------------
	$service->requestCode('wrong@example.net', '198.51.100.4', '');
	$real  = $email->codes['wrong@example.net'];
	$wrong = $wrongCodeFor($real);
	$a1 = $service->verifyCode('wrong@example.net', $wrong);
	$a2 = $service->verifyCode('wrong@example.net', $wrong);
	$failures += check('first two wrong codes are refused, code still alive', !$a1->success && !$a2->success
		&& $count('login_codes', "email = 'wrong@example.net' AND used_at IS NULL") === 1);
	$a3 = $service->verifyCode('wrong@example.net', $wrong);
	$failures += check('third wrong code cancels the code', !$a3->success
		&& $count('login_codes', "email = 'wrong@example.net' AND used_at IS NOT NULL AND attempts = 3") === 1);
	$late = $service->verifyCode('wrong@example.net', $real);
	$failures += check('the correct code then fails', !$late->success && $userModel->findByEmail('wrong@example.net') === null);
	$service->requestCode('wrong@example.net', '198.51.100.4', '');
	$fresh = $service->verifyCode('wrong@example.net', $email->codes['wrong@example.net']);
	$failures += check('a newly requested code works', $fresh->success && $fresh->get('status') === 'logged_in');

	// wrong@example.net is now a known user with no pending code.
	$noCodeKnown   = $service->verifyCode('wrong@example.net', '123456');
	$noCodeUnknown = $service->verifyCode('never.asked@example.org', '123456');
	$failures += check('same failure when no code is pending (known vs unknown)', !$noCodeKnown->success && $noCodeKnown->message === $noCodeUnknown->message);

	$service->requestCode('twice@example.net', '198.51.100.5', '');
	$real = $email->codes['twice@example.net'];
	$service->verifyCode('twice@example.net', $wrongCodeFor($real));
	$service->verifyCode('twice@example.net', $wrongCodeFor($real));
	$ok = $service->verifyCode('twice@example.net', $real);
	$failures += check('two wrong codes then the right one still works', $ok->success);

	// -----------------------------------------------------------------
	echo PHP_EOL . 'Expired and reused codes' . PHP_EOL;
	// -----------------------------------------------------------------
	$service->requestCode('expired@example.net', '198.51.100.6', '');
	$pdo->exec("UPDATE login_codes SET expires_at = '2000-01-01 00:00:00' WHERE email = 'expired@example.net'");
	$r = $service->verifyCode('expired@example.net', $email->codes['expired@example.net']);
	$failures += check('expired code is refused', !$r->success && $userModel->findByEmail('expired@example.net') === null);

	$service->requestCode('reuse@example.net', '198.51.100.7', '');
	$code = $email->codes['reuse@example.net'];
	$first  = $service->verifyCode('reuse@example.net', $code);
	$second = $service->verifyCode('reuse@example.net', $code);
	$failures += check('first use succeeds', $first->success);
	$failures += check('reused code is refused', !$second->success);

	$service->requestCode('resend@example.net', '198.51.100.8', '');
	$oldCode = $email->codes['resend@example.net'];
	$service->requestCode('resend@example.net', '198.51.100.8', '');
	$failures += check('a resend cancels the earlier unused code', $count('login_codes', "email = 'resend@example.net' AND used_at IS NULL") === 1);
	$r = $service->verifyCode('resend@example.net', $oldCode);
	$failures += check('the cancelled earlier code is refused', !$r->success);

	// -----------------------------------------------------------------
	echo PHP_EOL . 'Rate limit' . PHP_EOL;
	// -----------------------------------------------------------------
	$results = [];
	for ($i = 1; $i <= 6; $i++) {
		$results[$i] = $service->requestCode('limit@example.net', '203.0.113.9', '');
	}
	$failures += check('requests 1-5 from one IP succeed', $results[1]->success && $results[2]->success && $results[3]->success && $results[4]->success && $results[5]->success);
	$failures += check('the sixth request in an hour is refused as a rate-limit error', !$results[6]->success && $results[6]->isRateLimitError());
	$sentBefore = $email->loginCodeEmails;
	$service->requestCode('limit@example.net', '203.0.113.9', '');
	$failures += check('a refused request sends no email', $email->loginCodeEmails === $sentBefore);
	$other = $service->requestCode('limit@example.net', '203.0.113.10', '');
	$failures += check('a different IP is not limited', $other->success);

	$bad = $service->requestCode('not-an-email', '203.0.113.11', '');
	$failures += check('an invalid email address is refused', !$bad->success && $bad->isValidationError());

	// -----------------------------------------------------------------
	echo PHP_EOL . 'Terms (require_terms on)' . PHP_EOL;
	// -----------------------------------------------------------------
	$tenantsBefore = $count('tenants');
	$usersBefore   = $count('users');
	$termsSvc->requestCode('terms.person@startup.io', '198.51.100.20', '');
	$r = $termsSvc->verifyCode('terms.person@startup.io', $email->codes['terms.person@startup.io']);
	$failures += check('unknown email that verified its code is sent to the terms step', $r->success && $r->get('status') === 'terms_required');
	$verifiedAt = (string) $r->get('verified_at');
	$failures += check('no tenant or user is created until terms are accepted', $count('tenants') === $tenantsBefore && $count('users') === $usersBefore);

	$r = $termsSvc->acceptTerms('terms.person@startup.io', $verifiedAt, null);
	$failures += check('continuing without accepting is refused', !$r->success && $r->errorType === 'terms_required');
	$r = $termsSvc->acceptTerms('terms.person@startup.io', $verifiedAt, '0');
	$failures += check('anything other than "1" is refused', !$r->success);
	$failures += check('still nothing created', $count('tenants') === $tenantsBefore && $count('users') === $usersBefore);

	$r = $termsSvc->acceptTerms('terms.person@startup.io', '2000-01-01 00:00:00', '1');
	$failures += check('the terms step expires after 15 minutes', !$r->success && $count('users') === $usersBefore);

	$r = $termsSvc->acceptTerms('terms.person@startup.io', $verifiedAt, '1');
	$failures += check('accepting creates the tenant and owner and logs in', $r->success && $r->get('status') === 'logged_in'
		&& $count('tenants') === $tenantsBefore + 1 && $count('users') === $usersBefore + 1);
	$row = $userModel->findByEmail('terms.person@startup.io');
	$failures += check('acceptance time and terms_version are recorded', $row !== null && $row['terms_accepted_at'] !== null && $row['terms_version'] === 'test-1' && $row['role'] === 'owner');

	$termsSvc->requestCode('terms.person@startup.io', '198.51.100.21', '');
	$r = $termsSvc->verifyCode('terms.person@startup.io', $email->codes['terms.person@startup.io']);
	$failures += check('an existing user is never shown the terms step', $r->success && $r->get('status') === 'logged_in');

	// -----------------------------------------------------------------
	echo PHP_EOL . 'One email = one user' . PHP_EOL;
	// -----------------------------------------------------------------
	$tenantId = (int) $userModel->findByEmail('new.person@acme-corp.com')['tenant_id'];
	$otherTenantId = (new TenantService())->create('Other Co', 'other-co', 'x@other.co');
	$sqlState = null;
	try {
		$userModel->insertUser($otherTenantId, 'new.person@acme-corp.com', 'Dupe', 'user', gmdate('Y-m-d H:i:s'));
	} catch (PDOException $e) {
		$sqlState = (string) $e->getCode();
	}
	$failures += check('the same email cannot be inserted twice, even in another tenant', $sqlState === '23000');
	$failures += check('still exactly one row for that email', $count('users', "email = 'new.person@acme-corp.com'") === 1);
} finally {
	dropScratchDatabase($admin);
}

echo PHP_EOL . ($failures === 0 ? 'All login code checks passed.' : "{$failures} check(s) failed.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);

function check(string $label, bool $passed): int
{
	echo '  ' . ($passed ? 'PASS  ' : 'FAIL  ') . $label . PHP_EOL;
	return $passed ? 0 : 1;
}

function tableExists(PDO $pdo, string $table): bool
{
	return $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetch() !== false;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
	return $pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $pdo->quote($column))->fetch() !== false;
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
