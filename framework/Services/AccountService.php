<?php

declare(strict_types=1);

namespace Frank\Services;

use Frank\Core\Database;
use Frank\Models\User;
use Frank\Services\Email\EmailService;

/**
 * AccountService (v3.0.0)
 *
 * Framework-owned. The one place a new account (user, and its tenant when the
 * placement creates one) is created. Used by sign-up (LoginCodeService) and by
 * invites (InviteService).
 *
 * - Placement is decided by the SignupTenantResolver passed in: sign-up passes
 *   the app's resolver (default CreateTenantForSignup: new tenant, owner); an
 *   invite passes one for its kind.
 * - Everything runs in one Database::transaction(): placement, the user save,
 *   and the caller's $alsoInTransaction (an invite marks itself used there). A
 *   failure anywhere leaves nothing behind — no orphan tenant, no used invite.
 * - After the commit the platform owner is alerted.
 *
 * SoC contract: no Clock here — the caller passes the UTC timestamps.
 */
class AccountService
{
    public function __construct(
        private EmailService $emailService
    ) {
    }

    /**
     * Create the account.
     *
     * @param string               $email              Normalised email (EmailAddress::normalise)
     * @param SignupTenantResolver $placement          Decides tenant_id and role
     * @param string               $now                UTC created_at
     * @param string|null          $termsAcceptedAt    UTC acceptance time, or null when terms are off
     * @param string|null          $termsVersion       Stored only when terms were accepted
     *                                                 (trimmed, max 50 characters)
     * @param callable|null        $alsoInTransaction  Extra work that must succeed with the account;
     *                                                 throw to roll everything back
     * @param string               $via                How the account came about, for the alert
     *
     * Failure error type: 'system'.
     * Success data: user_id, tenant_id, role, email.
     */
    public function create(
        string $email,
        SignupTenantResolver $placement,
        string $now,
        ?string $termsAcceptedAt = null,
        ?string $termsVersion = null,
        ?callable $alsoInTransaction = null,
        string $via = 'sign-in code'
    ): ServiceResult {
        $name = $this->deriveNameFromEmail($email);

        try {
            $account = Database::transaction(function () use ($email, $name, $placement, $now, $termsAcceptedAt, $termsVersion, $alsoInTransaction): array {
                $placed   = $placement->resolve($email, $name);
                $tenantId = (int) $placed['tenant_id'];

                // Inherited from BaseModel: fill() then save().
                $user = new User();
                $user->fill([
                    'tenant_id'         => $tenantId,
                    'email'             => $email,
                    'name'              => $name,
                    'role'              => $placed['role'],
                    'created_at'        => $now,
                    'terms_accepted_at' => $termsAcceptedAt,
                    'terms_version'     => $termsAcceptedAt !== null ? $this->cleanTermsVersion($termsVersion) : null,
                ]);

                if (!$user->save()) {
                    throw new \RuntimeException('User save failed.');
                }

                // Read the saved user back (inherited where(), tenant-scoped): gives
                // the new id and confirms the save worked.
                $saved = User::where($tenantId, 'email', $email)[0] ?? null;
                if ($saved === null) {
                    throw new \RuntimeException('Saved user not found.');
                }

                if ($alsoInTransaction !== null) {
                    $alsoInTransaction();
                }

                return [
                    'user_id'     => (int) $saved->id,
                    'tenant_id'   => $tenantId,
                    'role'        => $placed['role'],
                    'tenant_name' => $placed['tenant_name'] ?? null,
                ];
            });
        } catch (\Throwable $e) {
            error_log('[AccountService] Account creation failed for ' . $email . ': ' . $e->getMessage());
            return ServiceResult::failure('Account creation failed. Please try again.', 'system');
        }

        $tenantLabel = $account['tenant_name'] !== null
            ? "\"{$account['tenant_name']}\" (tenant_id: {$account['tenant_id']})"
            : "tenant_id: {$account['tenant_id']}";

        $this->emailService->sendPlatformOwnerSignupAlert(
            $email,
            "A new account was created via {$via}. Tenant: {$tenantLabel}, role: {$account['role']}."
        );

        return ServiceResult::success('Account created.', [
            'user_id'   => $account['user_id'],
            'tenant_id' => $account['tenant_id'],
            'role'      => $account['role'],
            'email'     => $email,
        ]);
    }

    /**
     * The configured terms version as stored: trimmed, at most 50 characters
     * (users.terms_version is VARCHAR(50)), blank → null.
     */
    private function cleanTermsVersion(?string $termsVersion): ?string
    {
        if ($termsVersion === null || trim($termsVersion) === '') {
            return null;
        }

        return substr(trim($termsVersion), 0, 50);
    }

    private function deriveNameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0];
        return ucwords(str_replace(['.', '_', '-'], ' ', $localPart));
    }
}
