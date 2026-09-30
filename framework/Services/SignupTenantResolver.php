<?php

declare(strict_types=1);

namespace Frank\Services;

/**
 * SignupTenantResolver
 *
 * Policy point: decides which tenant a newly verified signup joins and with
 * what role. Called by SignupService::completeSignup() INSIDE the signup
 * transaction — any rows it writes roll back if the user insert fails.
 *
 * Framework default (v2.1.0): CreateTenantForSignup — every signup creates
 * a new tenant and the user becomes its owner.
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
