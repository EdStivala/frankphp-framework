<?php
namespace Frank\Controllers;

use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;
use Frank\Services\SignupService;
use Frank\Services\UserService;

class SignupController extends BaseController
{
	// ★ Services are injected by the container via bootstrap.php
	// v2.1.0: UserService added so auto-login records the login (accessed_at).
	public function __construct(
	private SignupService $signupService,
	private UserService $userService
	)
	{
	}

	public function showSignup(Request $request, array $params): mixed
	{
		if (session_status() === PHP_SESSION_NONE)
			session_start();
		if (!empty($_SESSION['user_id']))
			Response::redirect('/');
		return $this->view('auth/signup', []);
	}

	public function initiateSignup(Request $request, array $params): mixed
	{
		if (session_status() === PHP_SESSION_NONE)
			session_start();

		$data            = $request->bodyParams ?? $_POST;
		$email           = trim($data['email'] ?? '');
		$password        = $data['password'] ?? '';
		$confirmPassword = $data['password_confirm'] ?? '';
		$ipAddress       = $_SERVER['REMOTE_ADDR'] ?? '';
		$userAgent       = $_SERVER['HTTP_USER_AGENT'] ?? '';
		// v2.1.0: passed through as-is — SignupService decides whether it is
		// required (signup.require_terms). The controller never reads the flag.
		$termsAccepted   = $data['terms_accepted'] ?? null;
		$termsAccepted   = is_bool($termsAccepted) ? $termsAccepted : ($termsAccepted !== null ? (string) $termsAccepted : null);

		// ★ Use injected service
		$result = $this->signupService->initiateSignup(
		$email, $password, $confirmPassword, $ipAddress, $userAgent, $termsAccepted
		);

		if (!$result->success) {
			return $this->view('auth/signup', [
				'error'          => $result->message,
				'email'          => $email,
				'terms_accepted' => $termsAccepted,
			]);
		}

		$_SESSION['signup_email'] = $result->get('email');
		Response::redirect('/signup/verify');
	}

	public function showVerify(Request $request, array $params): mixed
	{
		if (session_status() === PHP_SESSION_NONE)
			session_start();
		if (empty($_SESSION['signup_email']))
			Response::redirect('/signup');

		return $this->view('auth/signup-verify', [
			'email' => $_SESSION['signup_email'],
		]);
	}

	public function completeSignup(Request $request, array $params): mixed
	{
		if (session_status() === PHP_SESSION_NONE)
			session_start();
		if (empty($_SESSION['signup_email']))
			Response::redirect('/signup');

		$email = $_SESSION['signup_email'];
		$data  = $request->bodyParams ?? $_POST;
		$code  = trim($data['code'] ?? '');

		// ★ Use injected service
		$result = $this->signupService->completeSignup($email, $code);

		if (!$result->success) {
			return $this->view('auth/signup-verify', [
				'email' => $email,
				'error' => $result->message,
			]);
		}

		unset($_SESSION['signup_email']);
		session_regenerate_id(true);

		$data = $result->data;
		$_SESSION['user_id'] = $data['user_id'];
		$tenantId = (int) $data['tenant_id'];

		// Auto-login is a login — record it, as AuthController::login() does.
		// Unlike a normal login, the account already exists, so a failure here
		// is logged and never blocks the user.
		if (!$this->userService->recordLogin((int) $data['user_id'], $tenantId)) {
			error_log('[SignupController] recordLogin failed for new user_id ' . $data['user_id']);
		}

		Response::redirect("/tenant/{$tenantId}/dashboard");
	}

	public function resendCode(Request $request, array $params): mixed
	{
		if (session_status() === PHP_SESSION_NONE)
			session_start();
		if (empty($_SESSION['signup_email']))
			Response::redirect('/signup');

		$email     = $_SESSION['signup_email'];
		$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

		// ★ Use injected service
		$result = $this->signupService->resendCode($email, $ipAddress);

		return $this->view('auth/signup-verify', [
			'email'   => $email,
			'success' => $result->success
			? 'A new code has been sent to ' . htmlspecialchars($email) . '.'
			: $result->message,
		]);
	}
}