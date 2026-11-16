<?php

declare(strict_types=1);

namespace Frank\Services;

/**
 * SignupTenantResolver
 *
 * Policy point: decides which tenant a newly verified signup joins and with
 * what role. Called by LoginCodeService INSIDE the account-creation
 * transaction — any rows it writes roll back if the user insert fails.
 *
 * Framework default (v2.1.0): CreateTenantForSignup — every new person (an
 * unknown email that verified its sign-in code, v3.0.0) creates a new tenant and the user becomes its owner.
 *
 * Apps with a different model (e.g. invite-into-existing-tenant) replace it
 * in app/bootstrap.php:
 *
 *   $container->override(\Frank\Services\SignupTenantResolver::class,
 *       fn ($c) => new \App\Services\MyTenantResolver());
 */
interface SignupTenantResolver
{
    /**
     * @param string $email  Verified, normalised (lowercase) email
     * @param string $name   Display name derived for the user
     * @return array{tenant_id: int, role: string, tenant_name?: string}
     */
    public function resolve(string $email, string $name): array;
}
