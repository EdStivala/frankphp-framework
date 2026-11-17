<?php
$title = 'Users — ' . htmlspecialchars($tenant['name'] ?? '');
ob_start();
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4 class="mb-0">Users</h4>
        <p class="text-muted mb-0" style="font-size:0.8125rem;"><?= $crossTenant ? 'All tenants' : htmlspecialchars($tenant['name'] ?? '') ?></p>
    </div>
</div>

<?php if (($_GET['success'] ?? '') === 'invited') : ?>
    <div class="alert alert-success settings-alert">Invite sent.</div>
<?php elseif (($_GET['error'] ?? '') === 'invalid_email') : ?>
    <div class="alert alert-danger settings-alert">Please enter a valid email address.</div>
<?php elseif (($_GET['error'] ?? '') === 'account_exists') : ?>
    <div class="alert alert-danger settings-alert">That email address already has an account.</div>
<?php elseif (($_GET['error'] ?? '') === 'invite_open') : ?>
    <div class="alert alert-danger settings-alert">That email address already has an open invite.</div>
<?php elseif (($_GET['error'] ?? '') === 'invite_email_failed') : ?>
    <div class="alert alert-danger settings-alert">The invite email could not be sent — please try again.</div>
<?php elseif (($_GET['error'] ?? '') === 'invite_failed') : ?>
    <div class="alert alert-danger settings-alert">Could not save the invite — please try again.</div>
<?php elseif (($_GET['error'] ?? '') === 'invalid_request') : ?>
    <div class="alert alert-danger settings-alert">Invalid request. Please try again.</div>
<?php endif; ?>

<!-- Your details + KPI Stat Cards -->
<div class="row g-3 mb-4">

    <?php if (!empty($me)) : ?>
    <div class="col-12 col-lg-4">
        <div class="stat-card h-100 align-items-start">
            <div class="stat-card-icon stat-card-icon--primary">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="stat-card-body flex-grow-1">
                <div class="fw-semibold fs-5 text-truncate"><?= htmlspecialchars($me['name']) ?></div>
                <div class="stat-card-label mt-0 text-truncate"><?= htmlspecialchars($me['email']) ?></div>

                <div class="stat-card-label mt-3">Tenant</div>
                <div class="fw-semibold text-truncate"><?= htmlspecialchars($me['tenantName']) ?></div>

                <div class="stat-card-label mt-2">Last Login</div>
                <div class="fw-semibold"><?= htmlspecialchars($me['lastLoginLabel']) ?></div>
            </div>
            <a href="/tenant/<?= (int) $tenant['id'] ?>/account" class="btn btn-circular flex-shrink-0" title="Edit" aria-label="Edit your details">
                <i class="bi bi-pencil-square"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-12 col-lg-8">
    <div class="row g-3">

    <div class="col-6">
        <div class="stat-card position-relative">
            <div class="stat-card-icon stat-card-icon--primary">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-value"><?= $totalUsers ?></div>
                <div class="stat-card-label">Total Users</div>
            </div>
            <button type="button" class="btn btn-sm btn-brand-primary position-absolute top-0 end-0 m-3"
                data-bs-toggle="modal" data-bs-target="#inviteModal">
                <i class="bi bi-plus-lg me-1"></i>Invite
            </button>
        </div>
    </div>

    <div class="col-6">
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

    <div class="col-6">
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

    <div class="col-6">
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
    </div>

</div>

<!-- Invite modal (sprint 3: saves the invite and sends the invite email) -->
<div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/users/invite">
                <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="inviteModalLabel"><?= $crossTenant ? 'Invite a new tenant' : 'Invite a new user' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="invite-email">Email address</label>
                    <input type="email" class="form-control" id="invite-email" name="email" maxlength="255"
                        placeholder="name@example.com" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand-primary">Send invite</button>
                </div>
            </form>
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
                        <th></th>
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
                        <td>
                            <?php if ($row['role'] === 'owner') : ?>
                            <a href="/tenant/<?= (int) $tenant['id'] ?>/tenants/edit?edit_tenant_id=<?= (int) $row['tenantId'] ?>"
                                class="btn btn-circular" title="Edit tenant" aria-label="Edit tenant of <?= htmlspecialchars($row['email']) ?>">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <?php else : ?>
                            <a href="/tenant/<?= (int) $tenant['id'] ?>/users/edit?user_id=<?= (int) $row['id'] ?>&amp;user_tenant_id=<?= (int) $row['tenantId'] ?>"
                                class="btn btn-circular" title="Edit" aria-label="Edit <?= htmlspecialchars($row['email']) ?>">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <?php endif; ?>
                        </td>
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
