<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Services\Email;

use Frank\Services\Email\PHPMailer\PHPMailer;
use Frank\Services\Email\PHPMailer\Exception;
use Frank\Services\Email\PHPMailer\SMTP;
use Frank\Services\Email\Templates\PasswordResetEmailTemplate;

/**
 * EmailService
 *
 * Centralised service for all email operations.
 *
 * Credentials are no longer hardcoded as constructor defaults.
 * They are read from the application config array, which in turn
 * reads from .env via Env::get() / Env::require().
 *
 * Usage — inject config in a controller or service provider:
 *
 *   $config       = require APP_BASE_DIR . '/Config/config.php';
 *   $emailService = EmailService::fromConfig($config['mail']);
 *
 * Or construct manually (e.g. in tests):
 *
 *   $emailService = new EmailService(
 *       host:     'smtp.example.com',
 *       port:     587,
 *       username: 'user',
 *       password: 'secret',
 *       from:     'no-reply@example.com',
 *       fromName: 'My App',
 *       replyTo:  'support@example.com',
 *   );
 */
class EmailService
{
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private string $replyToEmail;
	private string $adminEmail;

    public function __construct(
        string $host,
        int    $port,
        string $username,
        string $password,
        string $from,
        string $fromName,
        string $replyTo,
        string $adminEmail,
    ) {
        $this->host         = $host;
        $this->port         = $port;
        $this->username     = $username;
        $this->password     = $password;
        $this->fromEmail    = $from;
        $this->fromName     = $fromName;
        $this->replyToEmail = $replyTo;
		$this->adminEmail	= $adminEmail;
    }

    /**
     * Named constructor — build from the 'mail' section of config.php.
     *
     * @param  array $mailConfig  $config['mail']
     */
    public static function fromConfig(array $mailConfig): static
    {
        return new static(
            host:     $mailConfig['host'],
            port:     $mailConfig['port'],
            username: $mailConfig['username'],
            password: $mailConfig['password'],
            from:     $mailConfig['from_address'],
            fromName: $mailConfig['from_name'],
            replyTo:  $mailConfig['reply_to'],
            adminEmail:	$mailConfig['admin_email'],
        );
    }

	/**
	* Send a signup email verification code.
	* Called by SignupService — credentials come from the service's own config,
	* not from the caller.
	*/
	public function sendSignupVerification(
	string $email,
	string $code,
	int    $expiryMinutes
	): EmailResult
	{
		$subject  = 'Your verification code';
		$htmlBody = \Frank\Services\Email\Templates\SignupVerificationEmailTemplate::buildHtml(
		$email, $code, $expiryMinutes
		);
		$textBody = \Frank\Services\Email\Templates\SignupVerificationEmailTemplate::buildText(
		$email, $code, $expiryMinutes
		);

		return $this->send(new EmailMessage(
		credentialsUserName:   $this->username,
		credentialsUserSecret: $this->password,
		to:                    $email,
		subject:               $subject,
		htmlBody:              $htmlBody,
		toName:                '',
		textBody:              $textBody,
		from:                  $this->fromEmail,
		fromName:              $this->fromName,
		replyTo:               $this->replyToEmail,
		));
	}

	/**
	* Alert the platform owner about a signup-related event (e.g. a new
	* verification code requested, or a new account successfully created).
	* Recipient is $this->adminEmail — sourced from MAIL_SITE_ADMIN in
	* .env via config['mail']['admin_email'], set in the constructor.
	* Not tenant-scoped: this is a single, platform-wide operator address,
	* not a per-tenant owner's address — despite what this method used to
	* be called.
	*/
	public function sendPlatformOwnerSignupAlert(
	string $triggeringEmail,
	string $event
	): EmailResult
	{
		$subject  = 'New Signup Alert';
		$htmlBody = \Frank\Services\Email\Templates\PlatformOwnerSignupAlertTemplate::buildHtml(
		$triggeringEmail, $event
		);
		$textBody = \Frank\Services\Email\Templates\PlatformOwnerSignupAlertTemplate::buildText(
		$triggeringEmail, $event
		);

		return $this->send(new EmailMessage(
		credentialsUserName:   $this->username,
		credentialsUserSecret: $this->password,
		to:                    $this->adminEmail,
		subject:               $subject,
		htmlBody:              $htmlBody,
		toName:                '',
		textBody:              $textBody,
		from:                  $this->fromEmail,
		fromName:              $this->fromName,
		replyTo:               $this->replyToEmail,
		));
	}

    // ----------------------------------------------------------------
    // Convenience send methods
    // ----------------------------------------------------------------

    public function sendPasswordReset(string $email, string $name, string $resetUrl): EmailResult
    {
        $subject  = 'Password Reset Request';
        $htmlBody = PasswordResetEmailTemplate::buildHtml($name, $resetUrl);
        $textBody = PasswordResetEmailTemplate::buildText($name, $resetUrl);

        return $this->send(new EmailMessage(
            credentialsUserName:   $this->username,
            credentialsUserSecret: $this->password,
            to:                    $email,
            subject:               $subject,
            htmlBody:              $htmlBody,
            toName:                $name,
            textBody:              $textBody,
            from:                  $this->fromEmail,
            fromName:              $this->fromName,
            replyTo:               $this->replyToEmail,
        ));
    }

    public function sendUserInvite(string $email, string $name, string $inviteUrl): EmailResult
    {
        $subject  = "You've been invited";
        $htmlBody = $this->buildUserInviteHtml($name, $inviteUrl);

        return $this->send(new EmailMessage(
            credentialsUserName:   $this->username,
            credentialsUserSecret: $this->password,
            to:                    $email,
            subject:               $subject,
            htmlBody:              $htmlBody,
            toName:                $name,
            from:                  $this->fromEmail,
            fromName:              $this->fromName,
            replyTo:               $this->replyToEmail,
        ));
    }

    public function sendNotification(string $email, string $name, string $subject, string $body): EmailResult
    {
        $htmlBody = $this->buildNotificationHtml($name, $body);

        return $this->send(new EmailMessage(
            credentialsUserName:   $this->username,
            credentialsUserSecret: $this->password,
            to:                    $email,
            subject:               $subject,
            htmlBody:              $htmlBody,
            toName:                $name,
            from:                  $this->fromEmail,
            fromName:              $this->fromName,
            replyTo:               $this->replyToEmail,
        ));
    }

    // ----------------------------------------------------------------
    // Core send — PHPMailer dispatch
    // ----------------------------------------------------------------

    public function send(EmailMessage $message): EmailResult
    {
        if (!$message->isValid()) {
            $this->logError('Invalid email message', $message->to);
            return EmailResult::failure('Invalid email message');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->CharSet   = 'UTF-8';
            $mail->Host      = $this->host;
            $mail->SMTPDebug = 0;
            $mail->SMTPAuth  = true;
            $mail->Port      = $this->port;
            $mail->Username  = $message->credentialsUserName;
            $mail->Password  = $message->credentialsUserSecret;

            $mail->isHTML(true);
            $mail->addAddress($message->to, $message->toName);
            $mail->setFrom($message->from, $message->fromName);
            $mail->Subject = $message->subject;
            $mail->Body    = $message->htmlBody;

            if (!empty($message->textBody)) {
                $mail->AltBody = $message->textBody;
            }

            $mail->send();

            $this->logSuccess($message->to, $message->subject);
            return EmailResult::success('Email sent successfully');

        } catch (\Exception $e) {
            $this->logError($e->getMessage(), $message->to);
            return EmailResult::failure('Failed to send email: ' . $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    public function getBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    private function logSuccess(string $to, string $subject): void
    {
        error_log(sprintf('[EmailService] SUCCESS — "%s" → %s at %s', $subject, $to, date('Y-m-d H:i:s')));
    }

    private function logError(string $error, string $to): void
    {
        error_log(sprintf('[EmailService] ERROR — %s → %s at %s', $error, $to, date('Y-m-d H:i:s')));
    }

    private function buildUserInviteHtml(string $name, string $inviteUrl): string
    {
        return "<html><body><h1>Welcome {$name}!</h1><p><a href='{$inviteUrl}'>Accept Invitation</a></p></body></html>";
    }

    private function buildNotificationHtml(string $name, string $body): string
    {
        return "<html><body><h1>Hello {$name}</h1><p>{$body}</p></body></html>";
    }
}
