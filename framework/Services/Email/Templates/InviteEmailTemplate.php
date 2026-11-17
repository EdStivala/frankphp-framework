<?php

namespace Frank\Services\Email\Templates;

/**
 * InviteEmailTemplate (v3.0.0)
 *
 * Builds the HTML and plain-text bodies for the invite email. One template
 * for both kinds of invite (joining a tenant, or a new tenant). Same basic
 * branding as LoginCodeEmailTemplate. Apps replace it through
 * config('mail.templates')['invite'] (see codebase.md §16.8).
 *
 * Provisional wording (sprint 3).
 */
class InviteEmailTemplate
{
    /**
     * Build the HTML email body
     *
     * @param string $email      Invited address (shown for reassurance)
     * @param string $inviteUrl  Full link to accept the invite
     * @param int    $expiryDays Days until the invite expires
     */
    public static function buildHtml(string $email, string $inviteUrl, int $expiryDays = 7): string
    {
        $safeEmail = htmlspecialchars($email);
        $safeUrl   = htmlspecialchars($inviteUrl);
        $year      = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You've been invited</title>
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

                            <h2 style="margin:0 0 12px;font-size:1.125rem;font-weight:700;color:#111827;">You've been invited</h2>
                            <p style="margin:0 0 32px;font-size:0.9375rem;color:#374151;line-height:1.7;">
                                You've been invited to join FrankPHP as <strong>{$safeEmail}</strong>. Use the button below to accept and sign in.
                            </p>

                            <!-- Button -->
                            <div style="text-align:center;margin-bottom:32px;">
                                <a href="{$safeUrl}" style="display:inline-block;background-color:#2B3A67;color:#FFFFFF;text-decoration:none;font-size:0.9375rem;font-weight:700;padding:14px 32px;border-radius:8px;">Accept invite</a>
                                <p style="margin:12px 0 0;font-size:0.8125rem;color:#6B7280;">Expires in <strong style="color:#374151;">{$expiryDays} days</strong> and works once</p>
                            </div>

                            <p style="margin:0 0 16px;font-size:0.8125rem;color:#6B7280;line-height:1.6;word-break:break-all;">
                                If the button doesn't work, copy this link into your browser:<br>{$safeUrl}
                            </p>

                            <p style="margin:0;font-size:0.875rem;color:#9CA3AF;line-height:1.6;">
                                If you weren't expecting this invite, you can safely ignore this email.
                            </p>
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
    public static function buildText(string $email, string $inviteUrl, int $expiryDays = 7): string
    {
        return <<<TEXT
        You've been invited
        ===================

        You've been invited to join FrankPHP as {$email}.

        Accept the invite and sign in here:
        {$inviteUrl}

        The invite expires in {$expiryDays} days and works once.

        If you weren't expecting this invite, you can safely ignore this email.

        -- FrankPHP Team
        TEXT;
    }
}
