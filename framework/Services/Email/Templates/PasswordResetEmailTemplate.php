<?php
namespace Frank\Services\Email\Templates;

/**
 * PasswordResetEmailTemplate
 *
 * Builds HTML and text email content for password reset emails.
 * Keeps email template logic separate from sending logic.
 */
class PasswordResetEmailTemplate
{
    /**
     * Build HTML email body for password reset
     */
    public static function buildHtml(string $name, string $resetUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
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

                            <h2 style="margin:0 0 16px;font-size:1.125rem;font-weight:700;color:#111827;">Password Reset Request</h2>

                            <p style="margin:0 0 16px;font-size:0.9375rem;color:#374151;line-height:1.7;">
                                Hello <strong>{$name}</strong>,
                            </p>
                            <p style="margin:0 0 32px;font-size:0.9375rem;color:#374151;line-height:1.7;">
                                We received a request to reset your password for your FrankPHP account.
                                Click the button below to create a new password:
                            </p>

                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-bottom:36px;">
                                        <a href="{$resetUrl}"
										style="display:inline-block;padding:14px 40px;background-color:#2B3A67;color:#FFFFFF;text-decoration:none;border-radius:6px;font-size:0.9375rem;font-weight:700;letter-spacing:0.01em;">
                                            Reset Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Fallback link box -->
							<div style="background-color:#FFF5F0;border-left:3px solid #2B3A67;border-radius:0 6px 6px 0;padding:14px 18px;margin-bottom:28px;">
                                <p style="margin:0 0 4px;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#9CA3AF;">Or copy this link into your browser</p>
                                <p style="margin:0;font-size:0.8125rem;word-break:break-all;">
								<a href="{$resetUrl}" style="color:#2B3A67;text-decoration:none;">{$resetUrl}</a>
                                </p>
                            </div>

                            <p style="margin:0 0 24px;font-size:0.875rem;color:#374151;line-height:1.6;">
                                <strong>This link will expire in 1 hour.</strong>
                            </p>

                            <p style="margin:0;font-size:0.875rem;color:#9CA3AF;line-height:1.6;">
                                If you didn't request this password reset, you can safely ignore this email.
                                Your password will not be changed.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#F9FAFB;border-top:1px solid #E5E7EB;border-radius:0 0 12px 12px;padding:20px 48px;text-align:center;">
                            <p style="margin:0;font-size:0.75rem;color:#9CA3AF;">
                                This is an automated message from FrankPHP. Please do not reply to this email.
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
     * Build plain text email body for password reset
     */
    public static function buildText(string $name, string $resetUrl): string
    {
        return <<<TEXT
Password Reset Request

Hello {$name},

We received a request to reset your password for your FrankPHP account.

To reset your password, click the following link:
{$resetUrl}

This link will expire in 1 hour.

If you didn't request this password reset, you can safely ignore this email. Your password will not be changed.

---
This is an automated message from FrankPHP. Please do not reply to this email.
TEXT;
    }
}
