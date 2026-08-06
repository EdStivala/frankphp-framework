<?php

namespace Frank\Services\Email\Templates;

/**
 * SignupVerificationEmailTemplate
 *
 * Builds the HTML and plain-text bodies for signup email verification.
 * Sends a 6-digit code rather than a link so the user stays in-session.
 */
class SignupVerificationEmailTemplate
{
    /**
     * Build the HTML email body
     *
     * @param string $email  Recipient email address (shown for reassurance)
     * @param string $code   6-digit verification code (plain, not hashed)
     * @param int    $expiry Minutes until the code expires
     */
    public static function buildHtml(string $email, string $code, int $expiry = 15): string
    {
        $safeEmail = htmlspecialchars($email);
        $safeCode  = htmlspecialchars($code);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email</title>
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

                            <h2 style="margin:0 0 12px;font-size:1.125rem;font-weight:700;color:#111827;">Verify your email address</h2>
                            <p style="margin:0 0 32px;font-size:0.9375rem;color:#374151;line-height:1.7;">
                                You're almost there! Enter the code below to confirm <strong>{$safeEmail}</strong> and complete your account setup.
                            </p>

                            <!-- Verification code block -->
							<div style="background-color:#F3F4F6;border:1px solid #2B3A67;border-radius:10px;padding:28px 24px;text-align:center;margin-bottom:32px;">
                                <p style="margin:0 0 12px;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#9CA3AF;">Your verification code</p>
								<span style="font-size:2.5rem;font-weight:900;letter-spacing:0.3em;color:#2B3A67;font-family:'Courier New',Courier,monospace;">{$safeCode}</span>
                                <p style="margin:12px 0 0;font-size:0.8125rem;color:#6B7280;">Expires in <strong style="color:#374151;">{$expiry} minutes</strong></p>
                            </div>

                            <p style="margin:0;font-size:0.875rem;color:#9CA3AF;line-height:1.6;">
                                If you didn't request this, you can safely ignore this email. Someone may have entered your address by mistake.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#F9FAFB;border-top:1px solid #E5E7EB;border-radius:0 0 12px 12px;padding:20px 48px;text-align:center;">
                            <p style="margin:0;font-size:0.75rem;color:#9CA3AF;">
                                &copy; <?= date('Y') ?> FrankPHP &mdash; This is an automated message, please do not reply.
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
    public static function buildText(string $email, string $code, int $expiry = 15): string
    {
        return <<<TEXT
        Verify your email address
        =========================

        You're almost there! Enter the code below to confirm {$email} and complete your FrankPHP account setup.

        Your verification code: {$code}

        This code expires in {$expiry} minutes.

        If you didn't request this, you can safely ignore this email.

        -- FrankPHP Team
        TEXT;
    }
}
