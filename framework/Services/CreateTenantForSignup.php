<?php

declare(strict_types=1);

namespace Frank\Services;

/**
 * CreateTenantForSignup
 *
 * Default SignupTenantResolver (FrankPHP v2.1.0): creates a new tenant for
 * every verified signup, named from the email domain (or "<Name>'s
 * Workspace" for free-mail addresses), and makes the user its owner.
 */
class CreateTenantForSignup implements SignupTenantResolver
{
    public function __construct(
        private TenantService $tenantService,
        private TenantNameGenerator $nameGenerator = new TenantNameGenerator(),
    ) {
    }

    public function resolve(string $email, string $name): array
    {
        $tenant   = $this->nameGenerator->fromEmail($email);
        $tenantId = $this->tenantService->create($tenant['name'], $tenant['slug'], $email);

        return [
            'tenant_id'   => $tenantId,
            'role'        => 'owner',
            'tenant_name' => $tenant['name'],
        ];
    }
}
