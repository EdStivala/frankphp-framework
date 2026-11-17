<?php
// Edit one tenant (platform only). Supplied by UserAdminController::showTenantEdit():
// $tenant, $user (the logged-in person) and $target (the tenant row being edited).
$pageTag = 'Users';
$title   = 'Edit Tenant';
ob_start();
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <a href="/tenant/<?= (int) $tenant['id'] ?>/users" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Users
    </a>
    <?php // Support access (v3.0.0): the framework's POST /tenant/{tenant_id}/support/start ?>
    <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/support/start" class="m-0">
        <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
        <input type="hidden" name="target_tenant_id" value="<?= (int) $target['id'] ?>">
        <button type="submit" class="btn btn-outline-primary">
            Capture Tenant<i class="bi bi-box-arrow-in-right ms-1"></i>
        </button>
    </form>
</div>

<div class="page-header">
    <h4 class="mb-0">Edit Tenant</h4>
    <p class="text-muted mb-0" style="font-size:0.8125rem;"><?= htmlspecialchars($target['name'] ?? '') ?></p>
</div>

<?php if (($_GET['success'] ?? '') === '1') : ?>
    <div class="alert alert-success settings-alert">Saved.</div>
<?php elseif (($_GET['error'] ?? '') === 'save_failed') : ?>
    <div class="alert alert-danger settings-alert">Could not save — please try again.</div>
<?php elseif (($_GET['error'] ?? '') === 'name_mismatch') : ?>
    <div class="alert alert-danger settings-alert">The name you typed does not match. Nothing was deleted.</div>
<?php elseif (($_GET['error'] ?? '') === 'delete_failed') : ?>
    <div class="alert alert-danger settings-alert">Could not delete this tenant — please try again.</div>
<?php elseif (($_GET['error'] ?? '') === 'invalid_request') : ?>
    <div class="alert alert-danger settings-alert">Invalid request. Please try again.</div>
<?php endif; ?>

<div class="settings-layout">
    <div class="settings-panel">

        <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/tenants/save">
            <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
            <input type="hidden" name="edit_tenant_id" value="<?= (int) $target['id'] ?>">

            <div class="settings-group">
                <div class="settings-group-title">Tenant details</div>

                <div class="mb-3">
                    <label class="form-label" for="edit-tenant-name">Tenant name</label>
                    <input type="text" class="form-control" id="edit-tenant-name" name="name"
                        value="<?= htmlspecialchars($target['name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="edit-company-email">Company email</label>
                    <input type="email" class="form-control" id="edit-company-email" name="company_email"
                        value="<?= htmlspecialchars($target['company_email'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="edit-timezone">Timezone</label>
                    <input type="text" class="form-control" id="edit-timezone" name="timezone"
                        value="<?= htmlspecialchars($target['timezone'] ?? '') ?>" placeholder="e.g. Europe/London">
                </div>

                <div class="mb-0">
                    <label class="form-label" for="edit-date-format">Date format</label>
                    <input type="text" class="form-control" id="edit-date-format" name="date_format"
                        value="<?= htmlspecialchars($target['date_format'] ?? '') ?>" placeholder="e.g. DD/MM/YYYY">
                </div>
            </div>

            <button type="submit" class="btn btn-brand-primary">Save changes</button>
        </form>

        <div class="settings-group mt-4">
            <div class="settings-group-title">Delete tenant</div>
            <div class="settings-group-description">Permanently removes:</div>
            <ul>
                <li>the tenant <strong><?= htmlspecialchars($target['name'] ?? '') ?></strong></li>
                <li>every user in it, including its owner</li>
            </ul>
            <p>This cannot be undone.</p>

            <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/tenants/delete" id="tenant-edit-delete-form">
                <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
                <input type="hidden" name="edit_tenant_id" value="<?= (int) $target['id'] ?>">
                <div class="mb-3">
                    <label class="form-label" for="tenant-edit-delete-name">
                        Type the tenant name <strong><?= htmlspecialchars($target['name'] ?? '') ?></strong> to confirm
                    </label>
                    <input type="text" class="form-control" id="tenant-edit-delete-name" name="tenant_name"
                        autocomplete="off" required>
                </div>

                <button type="submit" class="btn btn-danger" id="tenant-edit-delete-submit" disabled>Delete this tenant</button>
            </form>
        </div>

    </div>
</div>

<script>
// Convenience only: the delete button works once the tenant name is typed exactly.
(function () {
    var expected = <?= json_encode((string) ($target['name'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var input = document.getElementById('tenant-edit-delete-name');
    var button = document.getElementById('tenant-edit-delete-submit');
    input.addEventListener('input', function () { button.disabled = input.value !== expected; });
}());
</script>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/app-main.php';
?>
