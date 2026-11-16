<?php
/**
 * CSRF hidden field (v3.0.0). Same mechanism as the login forms: the token
 * lives in $_SESSION['csrf_token'] and the receiving controller compares it
 * with hash_equals(). Include inside every new POST <form>:
 *   <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
