<?php
/**
 * CSRF hidden field (v3.0.0). The token is made once per session by
 * Frank\Core\Session::csrfToken() and checked by Session::csrfValid() in the
 * receiving controller. Include inside every POST <form>:
 *   <?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
 */
?>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Frank\Core\Session::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
