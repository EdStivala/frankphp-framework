<?php

declare(strict_types=1);

namespace Frank\Models;

use Frank\Core\BaseModel;

/**
 * Invite (v3.0.0)
 *
 * DB access layer for the invites table. Framework-owned.
 *
 * tenant_id is always the sender's tenant, so every invite is tenant-scoped.
 * This model has no finders of its own: it is read and written only through
 * the methods it inherits from BaseModel — where(), find(), fill(), save().
 *
 * kind: 'join_tenant' (owner's invite: joins tenant_id as 'user') or
 *       'new_tenant'  (platform invite: gets a new tenant as 'owner').
 *
 * SoC contract (same as User): this model never calls Frank\Core\Clock;
 * timestamps are computed by InviteService and set as plain UTC strings.
 */
class Invite extends BaseModel
{
    protected string $table = 'invites';

    public ?int    $id                 = null;
    public int     $tenant_id;
    public string  $email;
    public string  $kind;
    public int     $invited_by_user_id;
    public string  $token_hash;
    public string  $expires_at;                // UTC DATETIME
    public ?string $created_at         = null; // UTC DATETIME
}
