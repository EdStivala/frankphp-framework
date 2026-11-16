<?php

declare(strict_types=1);

namespace Frank\Services;

use Frank\Core\Clock;
use Frank\Core\Database;
use Frank\Models\User;
use Frank\Services\Email\EmailService;

/**
 * LoginCodeService (v3.0.0)
 *
 * Framework-owned service behind the one sign-in / sign-up screen. A person
 * enters an email address and is emailed a 6-digit code (code only, no link).
 * There are no passwords.
 *
 * Rules (fixed by the product owner):
 * - A code lasts 15 minutes.
 * - 3 wrong guesses cancel that code; a new one must be requested.
 * - At most 5 code requests per hour per IP address (requests and resends).
 * - Known and unknown email addresses behave identically until the code is
 *   verified: same validation, same rate limit, same messages, and a code is
 *   emailed in both cases.
 * - Known email + correct code: the user is returned (the controller logs in).
 * - Unknown email + correct code: a tenant and its owner user are created
 *   straight away via SignupTenantResolver inside Database::transaction().
 * - Only when $config['signup']['require_terms'] is on, an unknown email that
 *   has verified its code must accept the Terms first (acceptTerms()); the
 *   tenant and user are created only after acceptance. Existing users are
 *   never asked.
 *
 * SoC contract:
 * - Clock is called HERE (Service layer); timestamps are passed to the User
 *   model as plain strings. The model never calls Clock.
 * - password_verify() and every limit comparison live here, not in the model.
 * - Name derivation from the email lives here.
 * - SQL NOW() is not used — every boundary is a PHP string produced by Clock.
 */
class LoginCodeService
{
    private User $userModel;
    private EmailService $emailService;
    private SignupTenantResolver $tenantResolver;
    private ?string $termsVersion;

    /**
     * @param bool        $requireTerms  $config['signup']['require_terms'] (off = no terms step)
     * @param string|null $termsVersion  $config['signup']['terms_version'], stored with the acceptance
     */
    public function __construct(
        ?User         $userModel    = null,
        ?EmailService $emailService = null,
        ?SignupTenantResolver $tenantResolver = null,
        private bool $requireTerms = false,
        ?string $termsVersion = null,
        private int $codeExpiryMinutes = 15,
        private int $maxAttempts       = 3,
        private int $rateLimitRequests = 5,
        private int $rateLimitMinutes  = 60
    ) {
        $this->userModel      = $userModel ?? new User();
        $this->emailService   = $emailService ?? throw new \LogicException(
            'LoginCodeService requires an EmailService. Register it in the container.'
        );
        $this->tenantResolver = $tenantResolver ?? new CreateTenantForSignup(new TenantService());
        $this->termsVersion   = ($termsVersion !== null && trim($termsVersion) !== '')
            ? substr(trim($termsVersion), 0, 50)
            : null;
    }

    // ----------------------------------------------------------------
    // Request a code (new request, or resend)
    // ----------------------------------------------------------------

    /**
     * Email a fresh code to $email. Used for the first request and for resend
     * — both count toward the per-IP limit and cancel earlier unused codes for
     * the address. Known and unknown addresses take exactly the same path.
     *
     * @param string $ipAddress  $_SERVER['REMOTE_ADDR'] from the controller
     * @param string $userAgent  $_SERVER['HTTP_USER_AGENT'] from the controller
     */
    public function requestCode(string $email, string $ipAddress = '', string $userAgent = ''): ServiceResult
    {
        $email = $this->normaliseEmail($email);

        if (!$this->isValidEmail($email)) {
            return ServiceResult::failure('Please enter a valid email address.', 'validation');
        }

        if ($this->isIpRateLimited($ipAddress)) {
            return ServiceResult::failure('Too many requests. Please try again later.', 'rate_limit');
        }

        $code      = $this->generateCode();
        $codeHash  = password_hash($code, PASSWORD_DEFAULT);
        $now       = Clock::nowUtcString();
        $expiresAt = Clock::utcOffsetString("+{$this->codeExpiryMinutes} minutes");

        $this->userModel->invalidatePendingLoginCodes($email, $now);
        $this->userModel->insertLoginCode($email, $codeHash, $expiresAt, $now, $ipAddress, $userAgent);

        $this->emailService->sendLoginCode($email, $code, $this->codeExpiryMinutes);

        // As in v2: the platform owner is alerted when a code is REQUESTED
        // by an address that has no account yet (a sign-up in progress).
        // The person requesting sees no difference.
        if (!$this->userModel->findByEmail($email)) {
            $this->emailService->sendPlatformOwnerSignupAlert(
                $email,
                'A new user has requested a signup verification code. Check the login_codes table.'
            );
        }

        return ServiceResult::success('Code sent.', ['email' => $email]);
    }

    // ----------------------------------------------------------------
    // Verify a code
    // ----------------------------------------------------------------

    /**
     * Check a submitted code.
     *
     * Success data `status`:
     * - 'logged_in'      — user_id, tenant_id, email. Known email, or unknown
     *                      email whose account was just created.
     * - 'terms_required' — email, verified_at (UTC). Unknown email, terms are
     *                      required, nothing has been created yet. The
     *                      controller keeps these in the session and calls
     *                      acceptTerms() once the person agrees.
     *
     * A guess is counted before the code is compared (atomic in the model), so
     * parallel requests cannot exceed the attempt cap. The cap-th wrong guess
     * cancels the code.
     */
    public function verifyCode(string $email, string $code): ServiceResult
    {
        $email = $this->normaliseEmail($email);
        $code  = trim($code);

        if ($email === '' || $code === '') {
            return ServiceResult::failure('Email and code are required.', 'validation');
        }

        $now     = Clock::nowUtcString();
        $pending = $this->userModel->findMostRecentPendingLoginCode($email, $now);

        if (!$pending || !$this->userModel->incrementLoginCodeAttempts((int) $pending['id'], $this->maxAttempts)) {
            return ServiceResult::failure(
                'The code is incorrect or has expired. Please request a new one.',
                'validation'
            );
        }

        if (!password_verify($code, $pending['code_hash'])) {
            if ((int) $pending['attempts'] + 1 >= $this->maxAttempts) {
                $this->userModel->markLoginCodeUsed((int) $pending['id'], $now);
                return ServiceResult::failure(
                    'Too many incorrect codes. Please request a new one.',
                    'validation'
                );
            }

            return ServiceResult::failure('The code is incorrect. Please try again.', 'validation');
        }

        // Claim the code. Only one of two racing correct submissions wins.
        if (!$this->userModel->markLoginCodeUsed((int) $pending['id'], $now)) {
            return ServiceResult::failure(
                'The code is incorrect or has expired. Please request a new one.',
                'validation'
            );
        }

        $user = $this->userModel->findByEmail($email);
        if ($user) {
            return $this->loggedIn((int) $user['id'], (int) $user['tenant_id'], $email);
        }

        if ($this->requireTerms) {
            return ServiceResult::success('Code verified.', [
                'status'      => 'terms_required',
                'email'       => $email,
                'verified_at' => $now,
            ]);
        }

        return $this->createAccount($email, $now);
    }

    // ----------------------------------------------------------------
    // Terms step (only when signup.require_terms is on)
    // ----------------------------------------------------------------

    /**
     * Whether a verified-awaiting-terms state is still inside its window
     * (same length as a code, 15 minutes by default).
     *
     * @param string $verifiedAt  UTC string returned by verifyCode()
     */
    public function isTermsStepOpen(string $verifiedAt): bool
    {
        return $verifiedAt !== ''
            && $verifiedAt >= Clock::utcOffsetString("-{$this->codeExpiryMinutes} minutes");
    }

    /**
     * Create the tenant + owner user for an email that has already verified
     * its code, once the person has accepted the Terms. Records acceptance
     * time (UTC) and terms version on the user.
     *
     * @param string           $verifiedAt  UTC string returned by verifyCode()
     * @param bool|string|null $accepted    posted `terms_accepted`; accepted means exactly '1' (or true)
     */
    public function acceptTerms(string $email, string $verifiedAt, bool|string|null $accepted): ServiceResult
    {
        $email = $this->normaliseEmail($email);

        if (!$this->isTermsStepOpen($verifiedAt)) {
            return ServiceResult::failure(
                'Your session has expired. Please start again.',
                'validation'
            );
        }

        if ($accepted !== true && $accepted !== '1') {
            return ServiceResult::failure(
                'You must accept the Terms & Conditions to continue.',
                'terms_required'
            );
        }

        // Someone created this account in the meantime: they have proved the
        // address, so just sign them in.
        $user = $this->userModel->findByEmail($email);
        if ($user) {
            return $this->loggedIn((int) $user['id'], (int) $user['tenant_id'], $email);
        }

        $now = Clock::nowUtcString();

        return $this->createAccount($email, $now, $now, $this->termsVersion);
    }

    // ----------------------------------------------------------------
    // Maintenance
    // ----------------------------------------------------------------

    /**
     * Delete expired login codes. Intended to be called from a cron job.
     *
     * @return int  Number of rows deleted
     */
    public function cleanupExpiredCodes(int $olderThanDays = 7): int
    {
        return $this->userModel->deleteExpiredLoginCodes(Clock::utcOffsetString("-{$olderThanDays} days"));
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Place the user via SignupTenantResolver (default: new tenant, owner) and
     * insert the user — one transaction, so a failure leaves no orphan tenant.
     */
    private function createAccount(
        string $email,
        string $now,
        ?string $termsAcceptedAt = null,
        ?string $termsVersion = null
    ): ServiceResult {
        $name = $this->deriveNameFromEmail($email);

        try {
            $account = Database::transaction(function () use ($email, $name, $now, $termsAcceptedAt, $termsVersion): array {
                $placement = $this->tenantResolver->resolve($email, $name);
                $tenantId  = (int) $placement['tenant_id'];

                // Inherited from BaseModel: fill() then save().
                $user = new User();
                $user->fill([
                    'tenant_id'         => $tenantId,
                    'email'             => $email,
                    'name'              => $name,
                    'role'              => $placement['role'],
                    'created_at'        => $now,
                    'terms_accepted_at' => $termsAcceptedAt,
                    'terms_version'     => $termsAcceptedAt !== null ? $termsVersion : null,
                ]);

                if (!$user->save()) {
                    throw new \RuntimeException('User save failed.');
                }

                // Read the saved user back (inherited where(), tenant-scoped): gives
                // the new id and confirms the save worked.
                $saved = User::where($tenantId, 'email', $email)[0] ?? null;
                if ($saved === null) {
                    throw new \RuntimeException('Saved user not found.');
                }
                $userId = (int) $saved->id;

                return [
                    'user_id'     => $userId,
                    'tenant_id'   => $tenantId,
                    'role'        => $placement['role'],
                    'tenant_name' => $placement['tenant_name'] ?? null,
                ];
            });
        } catch (\Throwable $e) {
            error_log('[LoginCodeService] Account creation failed for ' . $email . ': ' . $e->getMessage());
            return ServiceResult::failure('Account creation failed. Please try again.', 'system');
        }

        $tenantLabel = $account['tenant_name'] !== null
            ? "\"{$account['tenant_name']}\" (tenant_id: {$account['tenant_id']})"
            : "tenant_id: {$account['tenant_id']}";

        $this->emailService->sendPlatformOwnerSignupAlert(
            $email,
            "A new account was created via sign-in code. Tenant: {$tenantLabel}, role: {$account['role']}."
        );

        return $this->loggedIn($account['user_id'], $account['tenant_id'], $email);
    }

    private function loggedIn(int $userId, int $tenantId, string $email): ServiceResult
    {
        return ServiceResult::success('Signed in.', [
            'status'    => 'logged_in',
            'user_id'   => $userId,
            'tenant_id' => $tenantId,
            'email'     => $email,
        ]);
    }

    private function normaliseEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function isValidEmail(string $email): bool
    {
        return strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Cryptographically random 6-digit code, zero-padded.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Derive a display name from an email local-part.
     * e.g. "john.doe@example.com" → "John Doe"
     */
    private function deriveNameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0];
        return ucwords(str_replace(['.', '_', '-'], ' ', $localPart));
    }

    /**
     * Whether an IP address has used up its code requests for the window.
     * The threshold is computed here via Clock; the >= comparison is the
     * business rule and lives here, not in the model.
     */
    private function isIpRateLimited(string $ipAddress): bool
    {
        if ($ipAddress === '') {
            return false;
        }

        $threshold = Clock::utcOffsetString("-{$this->rateLimitMinutes} minutes");
        return $this->userModel->countRecentLoginCodesByIp($ipAddress, $threshold) >= $this->rateLimitRequests;
    }
}
