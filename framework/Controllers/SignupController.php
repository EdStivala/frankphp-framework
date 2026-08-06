<?php
namespace Frank\Controllers;

use Frank\Core\BaseController;
use Frank\Core\Request;
use Frank\Core\Response;
use Frank\Services\SignupService;

class SignupController extends BaseController
{
	// ★ Service is injected by the container via bootstrap.php
	public function __construct(
	private SignupService $signupService
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

		// ★ Use injected service
		$result = $this->signupService->initiateSignup(
		$email, $password, $confirmPassword, $ipAddress, $userAgent
		);

		if (!$result->success) {
			return $this->view('auth/signup', [
				'error' => $result->message,
				'email' => $email,
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