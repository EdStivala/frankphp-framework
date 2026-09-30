<?php
namespace App\Controllers;

use Frank\Core\BaseController;
use Frank\Core\Request;

/**
 * LegalController — app-owned public legal pages (starter, FrankPHP 2.1+).
 * The Terms & Conditions view is a placeholder: replace its content.
 */
class LegalController extends BaseController
{
	public function __construct(private array $config = [])
	{
	}

	public function terms(Request $request, array $params): mixed
	{
		return $this->view('auth/terms', [
			'termsVersion' => $this->config['signup']['terms_version'] ?? null,
		]);
	}
}
