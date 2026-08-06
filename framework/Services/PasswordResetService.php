<?php

declare(strict_types=1);

namespace Frank\Services;

use Frank\Core\Clock;
use Frank\Models\User;
use Frank\Services\Email\EmailService;

/**
 * PasswordResetService
 *
 * Framework-owned service. Handles all password reset business logic:
 * - Token generation and validation
 * - Rate limiting
 * - Email coordination via EmailService
 * - Password updates
 *
 * SoC contract (FrankPHP v1.3.2):
 * - Clock is called HERE (Service layer) to produce timestamp strings.
 * - Those strings are passed as plain parameters to User model methods.
 * - The User model never calls Clock.
 * - hash verification (password_verify) lives here — it is business logic,
 *   not DB access.
 */
class PasswordResetService
{
    private User $userModel;
    private EmailService $emailService;
    private int $tokenExpiryHours;
    private int $rateLimitAttempts;
    private int $rateLimitMinutes;

    public function __construct(
        ?User         $userModel    = null,
        ?EmailService $emailService = null,
        int $tokenExpiryHours  = 1,
        int $rateLimitAttempts = 3,
        int $rateLimitMinutes  = 60
    ) {
        $this->userModel      = $userModel ?? new User();
        $this->emailService   = $emailService ?? throw new \LogicException(
            'PasswordResetService requires an EmailService. Register it in the container.'
        );
        $this->tokenExpiryHours  = $tokenExpiryHours;
        $this->rateLimitAttempts = $rateLimitAttempts;
        $this->rateLimitMinutes  = $rateLimitMinutes;
    }

    // ----------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------

    /**
     * Request a password reset.
     *
     * Validates the email, checks rate limits, generates a token, stores it,
     * and sends the reset email. Always returns a success message to avoid
     * revealing whether the email address is registered.
     */
    public function requestPasswordReset(string $email): ServiceResult
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ServiceResult::failure(
                'Please enter a valid email address.',
                'validation',
                ['email' => $email]
            );
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            return ServiceResult::success(
                'If that email exists, you will receive reset instructions shortly.',
                ['email' => $email]
            );
        }

        if ($this->isRateLimited($user['id'])) {
            return ServiceResult::failure(
                'Too many reset requests. Please try again later.',
                'rate_limit',
                ['email' => $email]
            );
        }

        $token    = $this->generateToken($user['id'], $user['tenant_id']);
        $resetUrl = $this->emailService->getBaseUrl() . '/reset-password?token=' . urlencode($token);

        $emailResult = $this->emailService->sendPasswordReset(
            $user['email'],
            $user['name'],
            $resetUrl
        );

        if (!$emailResult->success) {
            error_log('[PasswordResetService] Failed to send reset email to: ' . $email);
        }

        return ServiceResult::success(
            'If that email exists, you will receive reset instructions shortly.',
            ['email' => $email]
        );
    }

    /**
     * Validate a password reset token from a URL.
     *
     * Returns the token payload (user_id, email, tenant_id) on success.
     */
    public function validateToken(string $token): ServiceResult
    {
        if (empty($token)) {
            return ServiceResult::failure('No reset token provided.', 'validation');
        }

        $tokenData = $this->findValidToken($token);

        if (!$tokenData) {
            return ServiceResult::failure(
                'This reset link is invalid or has expired.',
                'validation'
            );
        }

        return ServiceResult::success('Token is valid', [
            'token'     => $token,
            'email'     => $tokenData['email'],
            'user_id'   => $tokenData['user_id'],
            'tenant_id' => $tokenData['tenant_id'],
            'token_id'  => $tokenData['id'],
        ]);
    }

    /**
     * Reset a user's password using a valid token.
     */
    public function resetPassword(string $token, string $newPassword, string $confirmPassword): ServiceResult
    {
        if (empty($token) || empty($newPassword)) {
            return ServiceResult::failure('Missing required information.', 'validation');
        }

        if ($newPassword !== $confirmPassword) {
            return ServiceResult::failure(
                'Passwords do not match.',
                'validation',
                ['token' => $token]
            );
        }

        if (strlen($newPassword) < 8) {
            return ServiceResult::failure(
                'Password must be at least 8 characters long.',
                'validation',
                ['token' => $token]
            );
        }

        $tokenData = $this->findValidToken($token);

        if (!$tokenData) {
            return ServiceResult::failure(
                'This reset link is invalid or has expired.',
                'validation'
            );
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $now          = Clock::nowUtcString();

        $success = $this->userModel->updatePasswordHash(
            $tokenData['user_id'],
            $passwordHash,
            $now
        );

        if (!$success) {
            return ServiceResult::failure(
                'Failed to update password. Please try again.',
                'system',
                ['token' => $token]
            );
        }

        $this->userModel->markPasswordResetTokenAsUsed($tokenData['id'], $now);
        $this->userModel->invalidateAllUserPasswordResetTokens($tokenData['user_id'], $now);

        return ServiceResult::success(
            'Password has been reset successfully.',
            ['redirect' => '/login?reset=success']
        );
    }

    /**
     * Delete expired tokens. Intended to be called from a cron job.
     *
     * @return int  Number of rows deleted
     */
    public function cleanupExpiredTokens(int $olderThanDays = 7): int
    {
        $cutoff = Clock::utcOffsetString("-{$olderThanDays} days");
        return $this->userModel->deleteExpiredPasswordResetTokens($cutoff);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Generate a secure token, hash it, store it, and return the plain token.
     *
     * Clock is called here to produce $expiresAt and $createdAt, which are
     * passed as strings to the model. The model never calls Clock.
     */
    private function generateToken(int $userId, int $tenantId): string
    {
        $token     = bin2hex(random_bytes(32));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        $expiresAt = Clock::utcOffsetString("+{$this->tokenExpiryHours} hours");
        $createdAt = Clock::nowUtcString();

        $this->userModel->insertPasswordResetToken(
            $userId,
            $tenantId,
            $tokenHash,
            $expiresAt,
            $createdAt
        );

        return $token;
    }

    /**
     * Find and verify a token against the stored hash.
     *
     * password_verify() is business logic — it lives here, not in the model.
     * Clock is called here to produce $now, passed to the model as a string.
     */
    private function findValidToken(string $token): ?array
    {
        $now    = Clock::nowUtcString();
        $tokens = $this->userModel->findValidPasswordResetTokens($now);

        foreach ($tokens as $record) {
            if (password_verify($token, $record['token_hash'])) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Check whether a user has exceeded the reset request rate limit.
     *
     * The threshold is computed here via Clock and passed to the model.
     * The >= comparison is a business rule and lives here, not in the model.
     */
    private function isRateLimited(int $userId): bool
    {
        $threshold    = Clock::utcOffsetString("-{$this->rateLimitMinutes} minutes");
        $attemptCount = $this->userModel->countRecentPasswordResetTokens($userId, $threshold);
        return $attemptCount >= $this->rateLimitAttempts;
    }
}
