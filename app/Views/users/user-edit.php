<?php
// Edit one user (platform / owner). Supplied by UserAdminController::showUserEdit():
// $tenant, $user (the logged-in person) and $target (the User being edited).
$pageTag = 'Users';
$title   = 'Edit User';
ob_start();
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4 class="mb-0">Edit User</h4>
        <p class="text-muted mb-0" style="font-size:0.8125rem;"><?= htmlspecialchars($target->email) ?></p>
    </div>
    <a href="/tenant/<?= (int) $tenant['id'] ?>/users" class="btn btn-outline-secondary">Back to Users</a>
</div>

<?php if (($_GET['success'] ?? '') === '1') : ?>
    <div class="alert alert-success settings-alert">Saved.</div>
<?php elseif (($_GET['error'] ?? '') === 'save_failed') : ?>
    <div class="alert alert-danger settings-alert">Could not save — please try again.</div>
<?php elseif (($_GET['error'] ?? '') === 'delete_failed') : ?>
    <div class="alert alert-danger settings-alert">Could not delete this user — please try again.</div>
<?php elseif (($_GET['error'] ?? '') === 'invalid_request') : ?>
    <div class="alert alert-danger settings-alert">Invalid request. Please try again.</div>
<?php endif; ?>

<div class="settings-layout">
    <div class="settings-panel">

        <div class="settings-group">
            <div class="settings-group-title">Profile</div>
            <div class="settings-group-description">The name can be changed. Email and role cannot be changed here.</div>

            <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/users/save" class="mb-3">
                <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
                <input type="hidden" name="user_id" value="<?= (int) $target->id ?>">
                <input type="hidden" name="user_tenant_id" value="<?= (int) $target->tenant_id ?>">
                <div class="mb-3">
                    <label class="form-label" for="edit-name">Name</label>
                    <input type="text" class="form-control" id="edit-name" name="name" maxlength="255"
                        value="<?= htmlspecialchars($target->name) ?>" required>
                </div>

                <button type="submit" class="btn btn-brand-primary">Save name</button>
            </form>

            <div class="settings-toggle-row">
                <div>
                    <div class="settings-toggle-row-label">Email</div>
                </div>
                <div class="settings-toggle-row-control text-muted"><?= htmlspecialchars($target->email) ?></div>
            </div>

            <div class="settings-toggle-row">
                <div>
                    <div class="settings-toggle-row-label">Role</div>
                </div>
                <div class="settings-toggle-row-control text-muted text-capitalize"><?= htmlspecialchars((string) $target->role) ?></div>
            </div>
        </div>

        <div class="settings-group">
            <div class="settings-group-title">Delete user</div>
            <div class="settings-group-description">Permanently deletes this user. This cannot be undone.</div>
            <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/users/delete"
                onsubmit="return confirm('Are you sure? This cannot be undone.');">
                <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
                <input type="hidden" name="user_id" value="<?= (int) $target->id ?>">
                <input type="hidden" name="user_tenant_id" value="<?= (int) $target->tenant_id ?>">
                <button type="submit" class="btn btn-outline-danger">Delete user</button>
            </form>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/app-main.php';
?>
