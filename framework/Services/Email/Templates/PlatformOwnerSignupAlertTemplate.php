<?php

namespace Frank\Services\Email\Templates;

/**
* PlatformOwnerSignupAlertTemplate
*
* Builds the HTML and plain-text bodies for the platform-owner signup
* alert email — sent to the single, platform-wide admin address
* (MAIL_SITE_ADMIN in .env), not to any per-tenant owner.
*/
class PlatformOwnerSignupAlertTemplate
{
/**
* Build the HTML email body
*
* @param string $email  The email address of the user this event pertains to
* @param string $event  Plain English description of the event being reported
*/
	public static function buildHtml(string $email, string $event): string
	{
		$safeEmail = htmlspecialchars($email);
		$safeEvent = htmlspecialchars($event);
		$year      = date('Y');

		return
		<<<HTML
			<!DOCTYPE html>
				<html lang="en">
					<head>
						<meta charset="UTF-8">
						<meta name="viewport" content="width=device-width, initial-scale=1.0">
						<title>Notification : Signup Event</title>
					</head>
					<body style="margin:0;padding:0;background-color:#F3F4F6;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

						<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F3F4F6;padding:48px 20px;">
						<tr>
						<td align="center">
						<table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">

						<!-- Logo header -->
							<tr>
							<td style="background-color:#FFFFFF;border-radius:12px 12px 0 0;padding:32px 48px 24px;text-align:center;border-bottom:3px solid #2B3A67;">
								<span style="font-family:'Arial Rounded MT Bold','Arial Black',Arial,sans-serif;font-size:1.75rem;font-weight:900;color:#2B3A67;letter-spacing:-0.01em;">FrankPHP</span>
								</td>
							</tr>

						<!-- Body -->
							<tr>
								<td style="background-color:#FFFFFF;padding:40px 48px 36px;">

									<h2 style="margin:0 0 12px;font-size:1.125rem;font-weight:700;color:#111827;">A signup event has happened that may require your attention</h2>

									<!-- Event block -->
									<p style="margin:0 0 32px;font-size:0.9375rem;color:#374151;line-height:1.7;"> User : <strong>{$safeEmail}</strong></p>
									<p style="margin:0 0 32px;font-size:0.9375rem;color:#374151;line-height:1.7;"> Event : <strong>{$safeEvent}</strong></p>

								</td>
							</tr>

							<!-- Footer -->
							<tr>
								<td style="background-color:#F9FAFB;border-top:1px solid #E5E7EB;border-radius:0 0 12px 12px;padding:20px 48px;text-align:center;">
									<p style="margin:0;font-size:0.75rem;color:#9CA3AF;">
									&copy; {$year} FrankPHP &mdash; This is an automated message, please do not reply.
									</p>
								</td>
							</tr>

					</table>
				</td>
			</tr>
		</table>
	</body>
</html>
HTML;
}

/**
* Build the plain-text fallback body
*/
	public static function buildText(string $email, string $event): string
	{
		return <<<TEXT
FrankPHP Notification
======================

A signup event has happened on FrankPHP that may require your attention.

User Email : {$email}

What happened : {$event}

-- FrankPHP Team
TEXT;
	}
}
