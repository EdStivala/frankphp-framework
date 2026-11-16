<?php
$pageTag = 'TenantDelete';
$title   = 'Delete Organisation';
ob_start();
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4 class="mb-0">Delete Organisation</h4>
        <p class="text-muted mb-0" style="font-size:0.8125rem;"><?= htmlspecialchars($tenant['name'] ?? '') ?></p>
    </div>
</div>

<?php if (($_GET['error'] ?? '') === 'name_mismatch') : ?>
    <div class="alert alert-danger settings-alert">The name you typed does not match. Nothing was deleted.</div>
<?php elseif (($_GET['error'] ?? '') === 'invalid_request') : ?>
    <div class="alert alert-danger settings-alert">Invalid request. Please try again.</div>
<?php elseif (($_GET['error'] ?? '') === 'delete_failed') : ?>
    <div class="alert alert-danger settings-alert">Could not delete the organisation — please try again.</div>
<?php endif; ?>

<div class="settings-layout">
    <div class="settings-panel">

        <div class="settings-group">
            <div class="settings-group-title">This cannot be undone</div>
            <div class="settings-group-description">Deleting this organisation permanently removes:</div>
            <ul>
                <li>the organisation <strong><?= htmlspecialchars($tenant['name'] ?? '') ?></strong></li>
                <li>every user in it, including you</li>
            </ul>
            <p class="mb-0">This cannot be undone.</p>
        </div>

        <div class="settings-group">
            <form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/tenant-delete" id="tenant-delete-form">
                <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
                <div class="mb-3">
                    <label class="form-label" for="tenant-delete-name">
                        Type the organisation name <strong><?= htmlspecialchars($tenant['name'] ?? '') ?></strong> to confirm
                    </label>
                    <input type="text" class="form-control" id="tenant-delete-name" name="tenant_name"
                        autocomplete="off" required>
                </div>

                <button type="submit" class="btn btn-danger" id="tenant-delete-submit" disabled>Delete this organisation</button>
                <a href="/tenant/<?= (int) $tenant['id'] ?>/tenant-settings" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>

    </div>
</div>

<script>
// Convenience only: the server checks the typed name again and is the check that counts.
(function () {
    var expected = <?= json_encode((string) ($tenant['name'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var input = document.getElementById('tenant-delete-name');
    var button = document.getElementById('tenant-delete-submit');
    input.addEventListener('input', function () { button.disabled = input.value !== expected; });
}());
</script>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/app-main.php';
?>
