<?php
$title = 'Users — ' . htmlspecialchars($tenant['name'] ?? '');
$canManage = in_array($user['role'] ?? '', ['owner', 'admin', 'platform'], true);
ob_start();
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4 class="mb-0">Users</h4>
        <p class="text-muted mb-0" style="font-size:0.8125rem;"><?= $crossTenant ? 'All tenants' : htmlspecialchars($tenant['name'] ?? '') ?></p>
    </div>
</div>

<?php
$success = [
    'user_deleted' => 'User deleted.',
    'role_changed' => 'Role updated.',
];
$errors = [
    'invalid_request'         => 'Invalid request. Please try again.',
    'user_not_found'          => 'That user could not be found.',
    'owner_cannot_be_deleted' => "The owner's account cannot be deleted here. It is removed by deleting the tenant, from Organisation Settings.",
    'use_account_settings'    => 'To delete your own account, use Account Settings.',
    'role_invalid'            => 'Choose a valid role.',
    'role_protected'          => 'The role of an owner or platform user cannot be changed.',
    'role_failed'             => 'Could not change the role — please try again.',
    'delete_failed'           => 'Could not delete the user — please try again.',
];
?>
<?php if (isset($success[$_GET['success'] ?? ''])) : ?>
    <div class="alert alert-success settings-alert"><?= htmlspecialchars($success[$_GET['success']]) ?></div>
<?php elseif (isset($errors[$_GET['error'] ?? ''])) : ?>
    <div class="alert alert-danger settings-alert"><?= htmlspecialchars($errors[$_GET['error']]) ?></div>
<?php endif; ?>

<!-- KPI Stat Cards -->
<div class="row g-3 mb-4">

    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon--primary">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-value"><?= $totalUsers ?></div>
                <div class="stat-card-label">Total Users</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon--success">
                <i class="bi bi-activity"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-value"><?= $activePercent ?>%</div>
                <div class="stat-card-label">Active Users</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon--warning">
                <i class="bi bi-person-slash"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-value"><?= $neverLoggedIn ?></div>
                <div class="stat-card-label">Never Logged In</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon--danger">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-value"><?= $inactiveThirtyDays ?></div>
                <div class="stat-card-label">Inactive 30+ Days</div>
            </div>
        </div>
    </div>

</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header-modern">
        <h5><i class="bi bi-person me-2"></i>All Users</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($users)) : ?>
            <div class="text-center py-5 text-muted" style="font-size:0.875rem;">
                <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:0.75rem;opacity:0.35;"></i>
                <?= $crossTenant ? 'No users found.' : 'No users found for this tenant.' ?>
            </div>
        <?php else : ?>
        <div class="table-responsive">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Name</th>
                        <?php if ($crossTenant) : ?><th>Tenant</th><?php endif; ?>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Last Login</th>
                        <?php if ($canManage) : ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $row) : ?>
                    <tr>
                        <td class="col-email"><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <?php if ($crossTenant) : ?><td><?= htmlspecialchars($row['tenantName']) ?></td><?php endif; ?>
                        <td>
                            <span class="role-badge role-badge--<?= htmlspecialchars($row['role']) ?>">
                                <?= htmlspecialchars($row['role']) ?>
                            </span>
                        </td>
                        <td class="col-date"><?= htmlspecialchars($row['joinedLabel']) ?></td>
                        <td class="<?= $row['hasLoggedIn'] ? 'col-date' : 'col-never' ?>">
                            <?= htmlspecialchars($row['lastLoginLabel']) ?>
                        </td>
                        <?php if ($canManage) : ?>
                        <td>
                            <?php if (!in_array($row['role'], ['owner', 'platform'], true)) : ?>
                            <div class="d-flex align-items-center gap-2">
                                <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/users/role" class="d-inline-flex gap-1">
                                    <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="tenant_id" value="<?= (int) $row['tenantId'] ?>">
                                    <select name="role" class="form-select form-select-sm" aria-label="Role for <?= htmlspecialchars($row['email']) ?>">
                                        <?php if (!in_array($row['role'], ['user', 'admin'], true)) : ?>
                                            <option value="" selected disabled><?= htmlspecialchars($row['role']) ?></option>
                                        <?php endif; ?>
                                        <option value="user"<?= $row['role'] === 'user' ? ' selected' : '' ?>>user</option>
                                        <option value="admin"<?= $row['role'] === 'admin' ? ' selected' : '' ?>>admin</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Change</button>
                                </form>
                                <?php if ((int) $row['id'] !== (int) $user['id']) : ?>
                                <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/users/delete" class="d-inline"
                                    onsubmit="return confirm('Are you sure? This cannot be undone.');">
                                    <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="tenant_id" value="<?= (int) $row['tenantId'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/app-main.php';
?>
