<?php

declare(strict_types=1);

namespace Frank\Services;

use Frank\Core\Clock;
use Frank\Core\Database;
use Frank\Models\Invite;
use Frank\Models\User;
use Frank\Services\Email\EmailAddress;
use Frank\Services\Email\EmailService;

/**
 * InviteService (v3.0.0)
 *
 * Framework-owned operations for invites. The app decides who may invite and
 * which kind of invite they send (the template app: an owner sends
 * 'join_tenant', a 'platform' user sends 'new_tenant').
 *
 * Rules (fixed by the product owner):
 * - 'join_tenant': the invited person joins the sender's tenant as 'user'.
 *   'new_tenant': the invited person gets a new tenant of their own as 'owner'.
 * - Refused if the address already has an account.
 * - Refused if the address already has a live (not expired) invite from the
 *   sender's tenant. Expired invites do not block a new invite.
 * - An invite lasts 7 days, works once and cannot be cancelled. It is deleted
 *   when accepted: the account then holds the record.
 *
 * Tenancy: every invite carries the sender's tenant_id and is read only with
 * the inherited tenant-scoped where(). Two different tenants may each invite
 * the same address; whichever link is used first creates the account and the
 * other link is then refused because the address has an account.
 *
 * SoC contract: Clock is called HERE. The link's token never leaves this
 * service except in the invite email; only its SHA-256 hash is stored.
 *
 * Email: the invite is saved and the email sent (link /invite/{tenant_id}/{token},
 * EmailService::sendInvite) in one transaction. If the send fails, nothing
 * is saved and the caller gets 'email_failed'.
 */
class InviteService
{
    public const KIND_JOIN_TENANT = 'join_tenant';
    public const KIND_NEW_TENANT  = 'new_tenant';

    private User $userModel;

    /**
     * @param bool        $requireTerms  $config['signup']['require_terms'] (same setting as sign-up)
     * @param string|null $termsVersion  $config['signup']['terms_version'] (AccountService trims it to the column)
     */
    public function __construct(
        private EmailService   $emailService,
        private AccountService $accountService,
        private TenantService  $tenantService,
        ?User $userModel = null,
        private bool $requireTerms = false,
        private ?string $termsVersion = null,
        private int $expiryDays = 7
    ) {
        $this->userModel = $userModel ?? new User();
    }

    /** Whether the welcome screen must ask for the terms (same setting as sign-up). */
    public function termsRequired(): bool
    {
        return $this->requireTerms;
    }

    /**
     * Create an invite.
     *
     * @param int    $tenantId         The sender's tenant
     * @param string $kind             self::KIND_JOIN_TENANT or self::KIND_NEW_TENANT
     * @param int    $invitedByUserId  The sender
     *
     * Failure error types: 'validation', 'account_exists', 'invite_open',
     * 'email_failed' (nothing saved), 'system'.
     * Success data: email, expires_at (UTC). The token is not returned.
     */
    public function createInvite(string $email, int $tenantId, string $kind, int $invitedByUserId): ServiceResult
    {
        $email = EmailAddress::normalise($email);

        if (!EmailAddress::isValid($email)) {
            return ServiceResult::failure('Please enter a valid email address.', 'validation');
        }

        if (!in_array($kind, [self::KIND_JOIN_TENANT, self::KIND_NEW_TENANT], true)) {
            return ServiceResult::failure('Unknown invite kind.', 'validation');
        }

        // Existing pattern: users.email is unique across the platform.
        if ($this->userModel->findByEmail($email)) {
            return ServiceResult::failure('That email address already has an account.', 'account_exists');
        }

        $now = Clock::nowUtcString();

        // Inherited, tenant-scoped where(): this tenant's invites to the address.
        foreach (Invite::where($tenantId, 'email', $email) as $earlier) {
            if ($this->isOpen($earlier, $now)) {
                return ServiceResult::failure('That email address already has an open invite.', 'invite_open');
            }
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = Clock::utcOffsetString("+{$this->expiryDays} days");

        // Inherited from BaseModel: fill() then save().
        $invite = new Invite();
        $invite->fill([
            'tenant_id'          => $tenantId,
            'email'              => $email,
            'kind'               => $kind,
            'invited_by_user_id' => $invitedByUserId,
            'token_hash'         => hash('sha256', $token),
            'expires_at'         => $expiresAt,
            'created_at'         => $now,
        ]);

        // The link is built with EmailService::getBaseUrl() (v2 password-reset pattern).
        $inviteUrl   = $this->emailService->getBaseUrl() . "/invite/{$tenantId}/{$token}";
        $emailFailed = false;

        // Save and send together (Database::transaction, as in LoginCodeService):
        // if the email cannot be sent, the save is rolled back, so no invite is
        // left blocking a retry.
        try {
            Database::transaction(function () use ($invite, $email, $inviteUrl, &$emailFailed): void {
                if (!$invite->save()) {
                    throw new \RuntimeException('Invite save failed.');
                }

                $sent = $this->emailService->sendInvite($email, $inviteUrl, $this->expiryDays);
                if (!$sent->success) {
                    $emailFailed = true;
                    throw new \RuntimeException('Invite email not sent.');
                }
            });
        } catch (\Throwable $e) {
            error_log('[InviteService] ' . $e->getMessage() . ' — ' . $email);

            return $emailFailed
                ? ServiceResult::failure('The invite email could not be sent. Please try again.', 'email_failed')
                : ServiceResult::failure('The invite could not be saved. Please try again.', 'system');
        }

        return ServiceResult::success('Invite sent.', [
            'email'      => $email,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Find an invite from its link (/invite/{tenant_id}/{token}) that can
     * still be used: it exists (not yet accepted) and has not expired.
     *
     * Failure error type: 'invite_unusable' (missing, used or expired — the
     * caller shows one screen for all three).
     * Success data: invite_id, tenant_id, email.
     */
    public function findOpenInvite(int $tenantId, string $token): ServiceResult
    {
        // Inherited, tenant-scoped where(): the token's hash within the tenant in the link.
        $invite = Invite::where($tenantId, 'token_hash', hash('sha256', $token))[0] ?? null;

        if ($invite === null || !$this->isOpen($invite, Clock::nowUtcString())) {
            return ServiceResult::failure('This invite has expired.', 'invite_unusable');
        }

        return ServiceResult::success('Invite found.', [
            'invite_id' => (int) $invite->id,
            'tenant_id' => (int) $invite->tenant_id,
            'email'     => $invite->email,
        ]);
    }

    /**
     * Accept an invite from the welcome screen: create the account and delete
     * the invite, together (AccountService::create, one transaction).
     *
     * Placement by kind: 'join_tenant' → the invite's tenant as 'user'
     * (JoinTenantAsUser); 'new_tenant' → a new tenant as 'owner'
     * (CreateTenantForSignup, the framework default sign-up placement).
     *
     * @param bool|string|null $accepted  posted `terms_accepted`; accepted means exactly '1' (or true).
     *                                    Ignored when terms are off.
     *
     * Failure error types: 'invite_unusable' (missing, used or expired),
     * 'terms_required', 'account_exists' (invite stays unused), 'system'.
     * Success data (from AccountService): user_id, tenant_id, role, email.
     */
    public function acceptInvite(int $tenantId, int $inviteId, bool|string|null $accepted): ServiceResult
    {
        $now = Clock::nowUtcString();

        // Inherited, tenant-scoped find().
        $invite = Invite::find($inviteId, $tenantId);
        if ($invite === null || !$this->isOpen($invite, $now)) {
            return ServiceResult::failure('This invite has expired.', 'invite_unusable');
        }

        if ($this->requireTerms && $accepted !== true && $accepted !== '1') {
            return ServiceResult::failure(
                'You must accept the Terms & Conditions to continue.',
                'terms_required'
            );
        }

        // Existing pattern: users.email is unique across the platform.
        if ($this->userModel->findByEmail($invite->email)) {
            return ServiceResult::failure(
                'That email address already has an account. Please sign in.',
                'account_exists'
            );
        }

        $placement = $invite->kind === self::KIND_NEW_TENANT
            ? new CreateTenantForSignup($this->tenantService)
            : new JoinTenantAsUser($tenantId);

        // Deleted inside the account transaction — the account now holds the
        // record. If the account fails, the delete rolls back and the invite
        // stays. Two people using the link at once cannot both succeed —
        // users.email is unique, so the second insert fails and rolls back.
        $deleteInvite = function () use ($invite): void {
            if (!$invite->delete()) { // inherited, tenant-scoped delete
                throw new \RuntimeException('Invite could not be deleted.');
            }
        };

        return $this->accountService->create(
            $invite->email,
            $placement,
            $now,
            $this->requireTerms ? $now : null,
            $this->requireTerms ? $this->termsVersion : null,
            $deleteInvite,
            'invite'
        );
    }

    /** Not expired. (A used invite no longer exists: it is deleted on acceptance.) */
    private function isOpen(Invite $invite, string $now): bool
    {
        return $invite->expires_at > $now;
    }
}
