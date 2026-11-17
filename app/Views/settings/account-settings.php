<?php
$pageTag = 'AccountSettings';
$title   = 'User Settings';
ob_start();
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4 class="mb-0">User Settings</h4>
        <p class="text-muted mb-0" style="font-size:0.8125rem;"><?= htmlspecialchars($settings['name'] ?? '') ?></p>
    </div>
</div>

<?php if (($_GET['success'] ?? '') === '1') : ?>
    <div class="alert alert-success settings-alert">Settings saved.</div>
<?php elseif (($_GET['error'] ?? '') === 'save_failed') : ?>
    <div class="alert alert-danger settings-alert">Could not save settings — please try again.</div>
<?php elseif (($_GET['error'] ?? '') === 'invalid_request') : ?>
    <div class="alert alert-danger settings-alert">Invalid request. Please try again.</div>
<?php elseif (($_GET['error'] ?? '') === 'delete_failed') : ?>
    <div class="alert alert-danger settings-alert">Could not delete your account — please try again.</div>
<?php endif; ?>

<div class="settings-layout">

    <nav class="settings-vnav">
        <a href="/tenant/<?= (int) $tenant['id'] ?>/account" class="settings-vnav-item active">Profile</a>
    </nav>

    <div class="settings-panel">

        <div class="settings-group">
            <div class="settings-group-title">
                Profile
                <span class="settings-scope-badge settings-scope-badge--personal">Just for you</span>
            </div>
            <div class="settings-group-description">You can change your name. Your email and role cannot be changed here.</div>

            <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/account/save" class="mb-3">
                <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
                <div class="mb-3">
                    <label class="form-label" for="settings-name">Name</label>
                    <input type="text" class="form-control" id="settings-name" name="name" maxlength="255"
                        value="<?= htmlspecialchars($settings['name'] ?? '') ?>" required>
                </div>

                <button type="submit" class="btn btn-brand-primary">Save name</button>
            </form>

            <div class="settings-toggle-row">
                <div>
                    <div class="settings-toggle-row-label">Email</div>
                </div>
                <div class="settings-toggle-row-control text-muted"><?= htmlspecialchars($settings['email'] ?? '') ?></div>
            </div>

            <div class="settings-toggle-row">
                <div>
                    <div class="settings-toggle-row-label">Role</div>
                </div>
                <div class="settings-toggle-row-control text-muted text-capitalize"><?= htmlspecialchars($settings['role'] ?? '') ?></div>
            </div>
        </div>

        <div class="settings-group">
            <div class="settings-group-title">
                Preferences
                <span class="settings-scope-badge settings-scope-badge--personal">Just for you</span>
            </div>
            <div class="settings-group-description">Overrides your organisation's default timezone. Leave blank to inherit it.</div>

            <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/account/save">
                <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
                <div class="mb-3">
                    <label class="form-label" for="settings-timezone">Timezone</label>
                    <input type="text" class="form-control" id="settings-timezone" name="timezone"
                        value="<?= htmlspecialchars($settings['timezone'] ?? '') ?>" placeholder="e.g. Europe/London">
                </div>

                <button type="submit" class="btn btn-brand-primary">Save changes</button>
            </form>
        </div>

        <?php if (($user['role'] ?? '') === 'user') : ?>
        <div class="settings-group">
            <div class="settings-group-title">Delete my account</div>
            <div class="settings-group-description">Permanently deletes your account. This cannot be undone.</div>
            <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/account/delete"
                onsubmit="return confirm('Are you sure? This cannot be undone.');">
                <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
                <button type="submit" class="btn btn-outline-danger">Delete my account</button>
            </form>
        </div>
        <?php endif; ?>

    </div>

</div>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/app-main.php';
?>
