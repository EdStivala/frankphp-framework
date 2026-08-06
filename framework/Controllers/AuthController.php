<?php

declare(strict_types=1);

namespace Frank\Controllers;

use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;
use Frank\Models\User;
use Frank\Services\PasswordResetService;
use Frank\Services\UserService;

/**
 * AuthController
 *
 * Framework-owned controller. Handles login, logout, and the full
 * password-reset flow.
 *
 * Dependencies injected via container (bootstrap.php):
 * - PasswordResetService
 * - UserService  (introduced v1.3.2 — owns recordLogin business logic)
 *
 * Controller responsibility: orchestration only.
 * - Reads request input, calls services, redirects or renders views.
 * - Never calls Clock directly.
 * - Never constructs services inline.
 */
class AuthController extends BaseController
{
    private const MAX_ATTEMPTS            = 5;
    private const LOCKOUT_SECONDS         = 900;
    private const INVALID_CREDENTIALS_MSG = 'Invalid email or password. Please try again.';
    private const INTERNAL_DATABASE_ERROR = 'An Internal Database Error occurred. Please try again.';

    public function __construct(
        private PasswordResetService $passwordResetService,
        private UserService          $userService
    ) {}

    // ----------------------------------------------------------------
    // Login
    // ----------------------------------------------------------------

    public function showLogin(Request $request, array $params): mixed
    {
        $this->startSession();
        $next = $_GET['next'] ?? '/';
        return $this->view('auth/login', ['next' => $next]);
    }

    public function login(Request $request, array $params): mixed
    {
        $this->startSession();

        $data           = $request->bodyParams ?? $_POST;
        $email          = trim($data['userEmail'] ?? '');
        $password       = $data['userPassword'] ?? '';
        $next           = $data['next'] ?? '/';
        $submittedEmail = $email;

        if (!$this->validateCsrfToken($data['csrf_token'] ?? '')) {
            return $this->loginError('Invalid request. Please try again.', $next, $submittedEmail);
        }

        $ipKey = 'login_attempts_' . $this->clientIp();
        if ($this->isLockedOut($ipKey)) {
            return $this->loginError(
                'Too many failed attempts. Please wait 15 minutes before trying again.',
                $next,
                $submittedEmail
            );
        }

        $userModel = new User();
        $user      = $userModel->findByEmail($email);

        $hash          = $user['password_hash'] ?? '$2y$10$invalidhashpadding000000000000000000000000000000000000000';
        $passwordValid = password_verify($password, $hash);

        if (!$user || !$passwordValid) {
            $this->recordFailedAttempt($ipKey);
            return $this->loginError(self::INVALID_CREDENTIALS_MSG, $next, $submittedEmail);
        }

        $this->clearFailedAttempts($ipKey);
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];

        $tenantId = (int) ($user['tenant_id'] ?? 0);

        // recordLogin is business logic — delegated to UserService.
        // UserService calls Clock and passes the timestamp to the model.
        if (!$this->userService->recordLogin($user['id'], $tenantId)) {
            return $this->loginError(self::INTERNAL_DATABASE_ERROR, $next, $submittedEmail);
        }

        Response::redirect("/tenant/{$tenantId}/dashboard");
    }

    public function logout(Request $request, array $params): mixed
    {
        $this->startSession();
        session_unset();
        session_destroy();
        Response::redirect('/login');
    }

    // ----------------------------------------------------------------
    // Password reset
    // ----------------------------------------------------------------

    public function showForgotPassword(Request $request, array $params): mixed
    {
        return $this->view('auth/forgot-password');
    }

    public function sendPasswordReset(Request $request, array $params): mixed
    {
        $this->startSession();

        $data  = $request->bodyParams ?? $_POST;
        $email = trim($data['email'] ?? '');

        $result = $this->passwordResetService->requestPasswordReset($email);

        if ($result->success) {
            return $this->view('auth/forgot-password-sent', [
                'email' => $result->get('email'),
            ]);
        }

        return $this->view('auth/forgot-password', [
            'error' => $result->message,
        ]);
    }

    public function showResetPassword(Request $request, array $params): mixed
    {
        $token  = $_GET['token'] ?? '';
        $result = $this->passwordResetService->validateToken($token);

        if (!$result->success) {
            return $this->view('auth/reset-password-error', [
                'message' => $result->message,
            ]);
        }

        return $this->view('auth/reset-password', [
            'token' => $result->get('token'),
            'email' => $result->get('email'),
        ]);
    }

    public function resetPassword(Request $request, array $params): mixed
    {
        $this->startSession();

        $data            = $request->bodyParams ?? $_POST;
        $token           = $data['token'] ?? '';
        $newPassword     = $data['password'] ?? '';
        $confirmPassword = $data['password_confirm'] ?? '';

        $result = $this->passwordResetService->resetPassword($token, $newPassword, $confirmPassword);

        if ($result->success) {
            $_SESSION['password_reset_success'] = true;
            Response::redirect($result->get('redirect', '/login?reset=success'));
        }

        if ($result->isValidationError() && $result->get('token')) {
            $validateResult = $this->passwordResetService->validateToken($result->get('token'));

            return $this->view('auth/reset-password', [
                'token' => $result->get('token'),
                'email' => $validateResult->get('email', ''),
                'error' => $result->message,
            ]);
        }

        return $this->view('auth/reset-password-error', [
            'message' => $result->message,
        ]);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function validateCsrfToken(string $submitted): bool
    {
        $stored = $_SESSION['csrf_token'] ?? '';
        return $stored !== '' && hash_equals($stored, $submitted);
    }

    private function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function isLockedOut(string $key): bool
    {
        $record = $_SESSION[$key] ?? null;
        if (!$record) {
            return false;
        }
        if ($record['attempts'] < self::MAX_ATTEMPTS) {
            return false;
        }

        $elapsed = time() - ($record['last_attempt'] ?? 0);
        if ($elapsed >= self::LOCKOUT_SECONDS) {
            $this->clearFailedAttempts($key);
            return false;
        }
        return true;
    }

    private function recordFailedAttempt(string $key): void
    {
        $record = $_SESSION[$key] ?? ['attempts' => 0, 'last_attempt' => 0];
        $record['attempts']++;
        $record['last_attempt'] = time();
        $_SESSION[$key]         = $record;
    }

    private function clearFailedAttempts(string $key): void
    {
        unset($_SESSION[$key]);
    }

    private function loginError(string $message, string $next, string $submittedEmail): mixed
    {
        return $this->view('auth/login', [
            'error'          => $message,
            'next'           => $next,
            'submittedEmail' => $submittedEmail,
        ]);
    }
}
