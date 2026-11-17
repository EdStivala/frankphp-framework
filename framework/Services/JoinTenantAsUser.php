<?php

declare(strict_types=1);

namespace Frank\Services;

/**
 * JoinTenantAsUser (v3.0.0)
 *
 * SignupTenantResolver for an owner's invite ('join_tenant'): the new person
 * joins the inviting tenant with role 'user'. Writes nothing itself; the
 * tenant already exists. Used by InviteService::acceptInvite().
 */
class JoinTenantAsUser implements SignupTenantResolver
{
    public function __construct(
        private int $tenantId
    ) {
    }

    public function resolve(string $email, string $name): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'role'      => 'user',
        ];
    }
}
