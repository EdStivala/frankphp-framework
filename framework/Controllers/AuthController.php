<?php

declare(strict_types=1);

namespace Frank\Controllers;

use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;
use Frank\Core\Session;
use Frank\Services\InviteService;
use Frank\Services\LoginCodeService;
use Frank\Services\ServiceResult;
use Frank\Services\UserService;

/**
 * AuthController (v3.0.0 — passwordless)
 *
 * Framework-owned controller. One screen (/login) signs people in and signs
 * new people up: they enter an email address, are emailed a 6-digit code, and
 * enter it on /login/verify. There are no passwords.
 *
 * Session state used by this flow:
 * - login_email  — the address a code was requested for (verify screen)
 * - login_terms  — ['email', 'verified_at'] after an unknown email verified its
 *                  code while signup.require_terms is on (terms screen)
 * - invite       — ['tenant_id', 'invite_id', 'email'] after a usable invite
 *                  link was opened (/invite/{tenant_id}/{token}); cleared
 *                  when the invite is accepted or turns out unusable
 *
 * Dependencies injected via container (bootstrap.php):
 * - LoginCodeService
 * - UserService    (recordLogin)
 * - InviteService  (invite link)
 *
 * Controller responsibility: orchestration only.
 * - Reads request input, calls services, redirects or renders views.
 * - Never calls Clock directly.
 * - Never constructs services inline.
 */
class AuthController extends BaseController
{
    private const INVALID_REQUEST_MSG = 'Invalid request. Please try again.';

    public function __construct(
        private LoginCodeService $loginCodeService,
        private UserService      $userService,
        private InviteService    $inviteService
    ) {}

    // ----------------------------------------------------------------
    // Step 1 — enter email
    // ----------------------------------------------------------------

    public function showLogin(Request $request, array $params): mixed
    {
        Session::start();
        return $this->view('auth/login', ['csrfToken' => $this->csrfToken()]);
    }

    /** POST /login — request a code. The redirect is the same for known and unknown emails. */
    public function login(Request $request, array $params): mixed
    {
        Session::start();

        $data  = $request->bodyParams ?? $_POST;
        $email = trim((string) ($data['email'] ?? ''));

        if (!$this->validateCsrfToken((string) ($data['csrf_token'] ?? ''))) {
            return $this->loginError(self::INVALID_REQUEST_MSG, $email);
        }

        $result = $this->loginCodeService->requestCode($email, $this->clientIp(), $this->userAgent());

        if (!$result->success) {
            return $this->loginError($result->message, $email);
        }

        $_SESSION['login_email'] = $result->get('email');
        Response::redirect('/login/verify');
    }

    // ----------------------------------------------------------------
    // Invite link (v3.0.0) — /invite/{tenant_id}/{token}
    // ----------------------------------------------------------------

    /**
     * GET /invite/{tenant_id}/{token}. No code is needed — the link went only
     * to the invited address. A usable invite is remembered in the session
     * and the welcome screen opens. Nothing is created on open: mail scanners
     * open links, so the account is created only when the person presses the
     * button (POST /invite/accept). Missing, used or expired → one error screen.
     */
    public function showInvite(Request $request, array $params): mixed
    {
        Session::start();

        $result = $this->inviteService->findOpenInvite(
            (int) ($params['tenant_id'] ?? 0),
            (string) ($params['token'] ?? '')
        );

        if (!$result->success) {
            unset($_SESSION['invite']);
            return $this->view('auth/invite-expired');
        }

        $_SESSION['invite'] = [
            'tenant_id' => $result->get('tenant_id'),
            'invite_id' => $result->get('invite_id'),
            'email'     => $result->get('email'),
        ];

        return $this->inviteWelcomeView();
    }

    /** POST /invite/accept — the welcome screen's button. */
    public function acceptInvite(Request $request, array $params): mixed
    {
        Session::start();

        $state = $_SESSION['invite'] ?? null;
        if (!is_array($state) || empty($state['invite_id']) || empty($state['tenant_id'])) {
            Response::redirect('/login');
        }

        $data = $request->bodyParams ?? $_POST;
        if (!$this->validateCsrfToken((string) ($data['csrf_token'] ?? ''))) {
            return $this->inviteWelcomeView(self::INVALID_REQUEST_MSG);
        }

        $accepted = $data['terms_accepted'] ?? null;
        $accepted = is_bool($accepted) ? $accepted : ($accepted !== null ? (string) $accepted : null);

        $result = $this->inviteService->acceptInvite(
            (int) $state['tenant_id'],
            (int) $state['invite_id'],
            $accepted
        );

        if (!$result->success) {
            switch ($result->errorType) {
                case 'invite_unusable':
                    unset($_SESSION['invite']);
                    return $this->view('auth/invite-expired');
                case 'account_exists':
                    unset($_SESSION['invite']);
                    return $this->loginError($result->message, (string) $state['email']);
                default: // terms_required, system
                    return $this->inviteWelcomeView($result->message);
            }
        }

        return $this->completeLogin($result); // clears the invite from the session
    }

    // ----------------------------------------------------------------
    // Step 2 — enter code
    // ----------------------------------------------------------------

    public function showVerify(Request $request, array $params): mixed
    {
        Session::start();
        if (empty($_SESSION['login_email'])) {
            Response::redirect('/login');
        }

        return $this->verifyView();
    }

    /** POST /login/verify */
    public function verify(Request $request, array $params): mixed
    {
        Session::start();
        if (empty($_SESSION['login_email'])) {
            Response::redirect('/login');
        }

        $data = $request->bodyParams ?? $_POST;
        if (!$this->validateCsrfToken((string) ($data['csrf_token'] ?? ''))) {
            return $this->verifyView(self::INVALID_REQUEST_MSG);
        }

        $result = $this->loginCodeService->verifyCode(
            $_SESSION['login_email'],
            trim((string) ($data['code'] ?? ''))
        );

        if (!$result->success) {
            return $this->verifyView($result->message);
        }

        if ($result->get('status') === 'terms_required') {
            $_SESSION['login_terms'] = [
                'email'       => $result->get('email'),
                'verified_at' => $result->get('verified_at'),
            ];
            unset($_SESSION['login_email']);
            Response::redirect('/login/terms');
        }

        return $this->completeLogin($result);
    }

    /** POST /login/resend — a new code; counts toward the per-IP limit. */
    public function resend(Request $request, array $params): mixed
    {
        Session::start();
        if (empty($_SESSION['login_email'])) {
            Response::redirect('/login');
        }

        $data = $request->bodyParams ?? $_POST;
        if (!$this->validateCsrfToken((string) ($data['csrf_token'] ?? ''))) {
            return $this->verifyView(self::INVALID_REQUEST_MSG);
        }

        $email  = $_SESSION['login_email'];
        $result = $this->loginCodeService->requestCode($email, $this->clientIp(), $this->userAgent());

        return $this->verifyView(
            $result->success ? null : $result->message,
            $result->success ? 'A new code has been sent to ' . $email . '.' : null
        );
    }

    // ----------------------------------------------------------------
    // Step 3 (only when signup.require_terms is on, unknown email only)
    // ----------------------------------------------------------------

    public function showTerms(Request $request, array $params): mixed
    {
        Session::start();
        if (!$this->termsStateIsOpen()) {
            Response::redirect('/login');
        }

        return $this->view('auth/login-terms', [
            'csrfToken' => $this->csrfToken(),
            'email'     => $_SESSION['login_terms']['email'],
        ]);
    }

    /** POST /login/terms */
    public function acceptTerms(Request $request, array $params): mixed
    {
        Session::start();
        if (!$this->termsStateIsOpen()) {
            Response::redirect('/login');
        }

        $data  = $request->bodyParams ?? $_POST;
        $state = $_SESSION['login_terms'];

        if (!$this->validateCsrfToken((string) ($data['csrf_token'] ?? ''))) {
            return $this->termsView($state['email'], self::INVALID_REQUEST_MSG);
        }

        $accepted = $data['terms_accepted'] ?? null;
        $accepted = is_bool($accepted) ? $accepted : ($accepted !== null ? (string) $accepted : null);

        $result = $this->loginCodeService->acceptTerms($state['email'], $state['verified_at'], $accepted);

        if (!$result->success) {
            return $this->termsView($state['email'], $result->message);
        }

        unset($_SESSION['login_terms']);
        return $this->completeLogin($result);
    }

    // ----------------------------------------------------------------
    // Logout
    // ----------------------------------------------------------------

    public function logout(Request $request, array $params): mixed
    {
        Session::start();
        Session::destroy();
        Response::redirect('/login');
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Establish the session for a verified person and redirect to their
     * dashboard. recordLogin is delegated to UserService (it calls Clock).
     */
    private function completeLogin(ServiceResult $result): mixed
    {
        $userId   = (int) $result->get('user_id');
        $tenantId = (int) $result->get('tenant_id');

        session_regenerate_id(true);
        unset($_SESSION['login_email'], $_SESSION['login_terms'], $_SESSION['invite']);
        $_SESSION['user_id'] = $userId;

        // The code is already spent and the session is established, so a
        // recordLogin failure is logged and never blocks the person.
        if (!$this->userService->recordLogin($userId, $tenantId)) {
            error_log('[AuthController] recordLogin failed for user_id ' . $userId);
        }

        Response::redirect("/tenant/{$tenantId}/dashboard");
    }

    private function termsStateIsOpen(): bool
    {
        $state = $_SESSION['login_terms'] ?? null;

        return is_array($state)
            && !empty($state['email'])
            && !empty($state['verified_at'])
            && $this->loginCodeService->isTermsStepOpen((string) $state['verified_at']);
    }

    private function verifyView(?string $error = null, ?string $success = null): mixed
    {
        return $this->view('auth/login-verify', [
            'csrfToken' => $this->csrfToken(),
            'email'     => $_SESSION['login_email'],
            'error'     => $error,
            'success'   => $success,
        ]);
    }

    private function termsView(string $email, string $error): mixed
    {
        return $this->view('auth/login-terms', [
            'csrfToken' => $this->csrfToken(),
            'email'     => $email,
            'error'     => $error,
        ]);
    }

    private function inviteWelcomeView(?string $error = null): mixed
    {
        return $this->view('auth/invite-welcome', [
            'csrfToken'    => $this->csrfToken(),
            'email'        => $_SESSION['invite']['email'] ?? '',
            'requireTerms' => $this->inviteService->termsRequired(),
            'error'        => $error,
        ]);
    }

    private function loginError(string $message, string $submittedEmail): mixed
    {
        return $this->view('auth/login', [
            'csrfToken'      => $this->csrfToken(),
            'error'          => $message,
            'submittedEmail' => $submittedEmail,
        ]);
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function validateCsrfToken(string $submitted): bool
    {
        $stored = $_SESSION['csrf_token'] ?? '';
        return $stored !== '' && hash_equals($stored, $submitted);
    }

    private function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    private function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}
