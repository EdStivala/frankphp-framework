<?php
$pageTag = 'TenantSettings';
$title   = 'Organisation Settings';
ob_start();
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4 class="mb-0">Organisation Settings</h4>
        <p class="text-muted mb-0" style="font-size:0.8125rem;"><?= htmlspecialchars($tenant['name'] ?? '') ?></p>
    </div>
</div>

<?php if (($_GET['success'] ?? '') === '1') : ?>
    <div class="alert alert-success settings-alert">Settings saved.</div>
<?php elseif (($_GET['error'] ?? '') === 'save_failed') : ?>
    <div class="alert alert-danger settings-alert">Could not save settings — please try again.</div>
<?php elseif (($_GET['error'] ?? '') === 'forbidden') : ?>
    <div class="alert alert-danger settings-alert">You don't have permission to view this page.</div>
<?php endif; ?>

<div class="settings-layout">

    <nav class="settings-vnav">
        <a href="/tenant/<?= (int) $tenant['id'] ?>/tenant-settings" class="settings-vnav-item active">General</a>
    </nav>

    <div class="settings-panel">
        <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/tenant-settings/save">

            <div class="settings-group">
                <div class="settings-group-title">
                    Organisation details
                    <span class="settings-scope-badge settings-scope-badge--org">Organisation-wide</span>
                </div>
                <div class="settings-group-description">Visible to every user in this tenant.</div>

                <div class="mb-3">
                    <label class="form-label" for="settings-name">Organisation name</label>
                    <input type="text" class="form-control" id="settings-name" name="name"
                        value="<?= htmlspecialchars($settings['name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="settings-company-email">Company email</label>
                    <input type="email" class="form-control" id="settings-company-email" name="company_email"
                        value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
                </div>
            </div>

            <div class="settings-group">
                <div class="settings-group-title">
                    Localisation
                    <span class="settings-scope-badge settings-scope-badge--org">Organisation-wide</span>
                </div>
                <div class="settings-group-description">Default timezone and date format for this tenant — inherited by users who haven't set their own.</div>

                <div class="mb-3">
                    <label class="form-label" for="settings-timezone">Timezone</label>
                    <input type="text" class="form-control" id="settings-timezone" name="timezone"
                        value="<?= htmlspecialchars($settings['timezone'] ?? '') ?>" placeholder="e.g. Europe/London">
                </div>

                <div class="mb-0">
                    <label class="form-label" for="settings-date-format">Date format</label>
                    <input type="text" class="form-control" id="settings-date-format" name="date_format"
                        value="<?= htmlspecialchars($settings['date_format'] ?? '') ?>" placeholder="e.g. DD/MM/YYYY">
                </div>
            </div>

            <button type="submit" class="btn btn-brand-primary">Save changes</button>
        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/app-main.php';
?>
