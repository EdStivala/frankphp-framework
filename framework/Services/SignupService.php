<?php

declare(strict_types=1);

namespace Frank\Services;

use Frank\Core\Clock;
use Frank\Models\User;
use Frank\Models\Tenant;
use Frank\Services\Email\EmailService;

/**
 * SignupService
 *
 * Framework-owned service. Handles all new-account signup business logic:
 *   1. Validate email + password
 *   2. Rate-limit by IP to deter bots
 *   3. Generate a 6-digit verification code, store hashed in signup_tokens
 *   4. Email the plain code to the user
 *   5. Verify the code on submission
 *   6. Create the user account
 *
 * SoC contract (FrankPHP v1.3.2):
 * - Clock is called HERE (Service layer) to produce timestamp strings.
 * - Those strings are passed as plain parameters to User model methods.
 * - The User model is the canonical DB access layer for signup_tokens and
 *   users — this service previously duplicated that access via raw PDO.
 *   All such duplication has been removed.
 * - password_verify() and the rate-limit decision (>= maxAttempts) are
 *   business logic and live here, not in the model.
 * - Name derivation from the email local-part is business logic and lives
 *   here, not in the model.
 * - SQL NOW() must not be used — all temporal boundaries are PHP strings
 *   produced by Clock.
 */
class SignupService
{
    private User $userModel;
    private EmailService $emailService;
    private int $codeExpiryMinutes;
    private int $rateLimitAttempts;
    private int $rateLimitMinutes;

    public function __construct(
        ?User         $userModel     = null,
        ?EmailService $emailService  = null,
        int $codeExpiryMinutes = 15,
        int $rateLimitAttempts = 5,
        int $rateLimitMinutes  = 60
    ) {
        $this->userModel       = $userModel ?? new User();
        $this->emailService    = $emailService ?? throw new \LogicException(
            'SignupService requires an EmailService. Register it in the container.'
        );
        $this->codeExpiryMinutes = $codeExpiryMinutes;
        $this->rateLimitAttempts = $rateLimitAttempts;
        $this->rateLimitMinutes  = $rateLimitMinutes;
    }

    // ----------------------------------------------------------------
    // Step 1 — validate, store pending token, send verification code
    // ----------------------------------------------------------------

    /**
     * Initiate a signup attempt.
     *
     * Validates the email and password, checks whether the email is already
     * registered, applies IP-based rate limiting, stores a hashed
     * verification code and hashed password in signup_tokens, and sends
     * the plain code to the user.
     *
     * @param string $email
     * @param string $password
     * @param string $confirmPassword
     * @param string $ipAddress  $_SERVER['REMOTE_ADDR'] from controller
     * @param string $userAgent  $_SERVER['HTTP_USER_AGENT'] from controller
     */
    public function initiateSignup(
        string $email,
        string $password,
        string $confirmPassword,
        string $ipAddress = '',
        string $userAgent = ''
    ): ServiceResult {

        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ServiceResult::failure(
                'Please enter a valid email address.',
                'validation'
            );
        }

        if (strlen($password) < 8) {
            return ServiceResult::failure(
                'Password must be at least 8 characters long.',
                'validation'
            );
        }

        if ($password !== $confirmPassword) {
            return ServiceResult::failure('Passwords do not match.', 'validation');
        }

        // Return success even when already registered — avoids revealing
        // whether an address is in the system (security).
        if ($this->userModel->findByEmail($email)) {
            return ServiceResult::success('Verification code sent.', ['email' => $email]);
        }

        if ($this->isIpRateLimited($ipAddress)) {
            return ServiceResult::failure(
                'Too many signup attempts. Please try again later.',
                'rate_limit'
            );
        }

        $code         = $this->generateCode();
        $codeHash     = password_hash($code, PASSWORD_DEFAULT);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $now          = Clock::nowUtcString();
        $expiresAt    = Clock::utcOffsetString("+{$this->codeExpiryMinutes} minutes");

        // Invalidate any previous unused tokens for this email, then insert fresh.
        $this->userModel->invalidatePendingSignupTokens($email, $now);

        $this->userModel->insertSignupToken(
            $email,
            $codeHash,
            $passwordHash,
            $expiresAt,
            $now,
            $ipAddress,
            $userAgent
        );

        $this->emailService->sendSignupVerification($email, $code, $this->codeExpiryMinutes);

        $this->emailService->sendPlatformOwnerSignupAlert(
            $email,
            'A new user has requested a signup verification code. Check sign-up token table.'
        );

        return ServiceResult::success('Verification code sent.', ['email' => $email]);
    }

    // ----------------------------------------------------------------
    // Step 2 — verify code and create account
    // ----------------------------------------------------------------

    /**
     * Complete signup by verifying the code and creating the user record.
     *
     * Looks up pending tokens, verifies the code hash (business logic —
     * stays in service), marks the token used, then inserts the new user.
     *
     * @param string $email
     * @param string $code  Plain 6-digit code from the form
     */
    public function completeSignup(string $email, string $code): ServiceResult
    {
        $email = strtolower(trim($email));
        $code  = trim($code);

        if (empty($email) || empty($code)) {
            return ServiceResult::failure(
                'Email and verification code are required.',
                'validation'
            );
        }

        $now   = Clock::nowUtcString();
        $token = $this->findAndVerifyToken($email, $code, $now);

        if (!$token) {
            return ServiceResult::failure(
                'The code is incorrect or has expired. Please request a new one.',
                'validation'
            );
        }

        // Race-condition guard.
        if ($this->userModel->findByEmail($email)) {
            $this->userModel->markSignupTokenUsed($token['id'], $now);
            return ServiceResult::failure(
                'An account with this email address already exists.',
                'validation'
            );
        }

        // Name derivation is business logic — computed here, passed to model.
        $name            = $this->deriveNameFromEmail($email);
        $defaultTenantId = 1; // Adjust to your tenant-assignment logic.

        $userId = $this->userModel->insertUser(
            $defaultTenantId,
            $email,
            $name,
            $token['password_hash'],
            'user',
            $now
        );

        if (!$userId) {
            return ServiceResult::failure(
                'Account creation failed. Please try again.',
                'system'
            );
        }

        $this->userModel->markSignupTokenUsed($token['id'], $now);

        $this->emailService->sendPlatformOwnerSignupAlert(
            $email,
            "A new account was created via signup (tenant_id: {$defaultTenantId})."
        );

        return ServiceResult::success('Account created successfully.', [
            'user_id'   => $userId,
            'email'     => $email,
            'tenant_id' => $defaultTenantId,
            'redirect'  => '/login?signup=success',
        ]);
    }

    // ----------------------------------------------------------------
    // Resend
    // ----------------------------------------------------------------

    /**
     * Resend a verification code to a pending signup email address.
     */
    public function resendCode(string $email, string $ipAddress = ''): ServiceResult
    {
        $email = strtolower(trim($email));

        if ($this->isIpRateLimited($ipAddress)) {
            return ServiceResult::failure(
                'Too many requests. Please wait before trying again.',
                'rate_limit'
            );
        }

        $now      = Clock::nowUtcString();
        $existing = $this->userModel->findMostRecentPendingSignupToken($email, $now);

        if (!$existing) {
            // Do not reveal whether a pending signup exists (security).
            return ServiceResult::success(
                'If there is a pending signup, a new code has been sent.',
                ['email' => $email]
            );
        }

        $code      = $this->generateCode();
        $codeHash  = password_hash($code, PASSWORD_DEFAULT);
        $expiresAt = Clock::utcOffsetString("+{$this->codeExpiryMinutes} minutes");

        $this->userModel->invalidatePendingSignupTokens($email, $now);

        $this->userModel->insertSignupToken(
            $email,
            $codeHash,
            $existing['password_hash'],
            $expiresAt,
            $now,
            $ipAddress,
            ''
        );

        $this->emailService->sendSignupVerification($email, $code, $this->codeExpiryMinutes);

        return ServiceResult::success('A new code has been sent.', ['email' => $email]);
    }

    // ----------------------------------------------------------------
    // Maintenance
    // ----------------------------------------------------------------

    /**
     * Delete expired signup tokens. Intended to be called from a cron job.
     *
     * @return int  Number of rows deleted
     */
    public function cleanupExpiredTokens(int $olderThanDays = 7): int
    {
        $cutoff = Clock::utcOffsetString("-{$olderThanDays} days");
        return $this->userModel->deleteExpiredSignupTokens($cutoff);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Cryptographically random 6-digit code, zero-padded.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Derive a display name from an email local-part.
     *
     * Business logic — lives in the service, not in the model.
     * e.g. "john.doe@example.com" → "John Doe"
     */
    private function deriveNameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0];
        return ucwords(str_replace(['.', '_', '-'], ' ', $localPart));
    }

    /**
     * Find pending token candidates for an email and verify the plain code
     * against stored hashes.
     *
     * password_verify() is business logic — it lives here, not in the model.
     * $now is passed to the model as a plain string; the model never calls Clock.
     */
    private function findAndVerifyToken(string $email, string $code, string $now): ?array
    {
        $rows = $this->userModel->findPendingSignupTokens($email, $now);

        foreach ($rows as $row) {
            if (password_verify($code, $row['code_hash'])) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Check whether an IP address has exceeded the signup rate limit.
     *
     * The threshold is computed here via Clock and passed to the model.
     * The >= comparison is a business rule and lives here, not in the model.
     */
    private function isIpRateLimited(string $ipAddress): bool
    {
        if ($ipAddress === '') {
            return false;
        }

        $threshold = Clock::utcOffsetString("-{$this->rateLimitMinutes} minutes");
        $count     = $this->userModel->countRecentSignupTokensByIp($ipAddress, $threshold);
        return $count >= $this->rateLimitAttempts;
    }
}
