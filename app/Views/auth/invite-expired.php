<?php
// Invite link that cannot be used (FrankPHP 3.0): missing, already used or
// expired — one screen for all three. Shown by AuthController::showInvite.
// Same card layout as login.php. Provisional wording.
$title = 'Invite expired - FrankPHP';

ob_start();
?>

<section class="d-flex align-items-center justify-content-center vh-100">

	<div class="card shadow login-card">
		<div class="card-body p-4">
			<h3 class="text-center mb-4">
				<img
				src="/assets/images/logo_full.svg"
				style="width: 200px;"
				alt="FrankPHP"
				/>
			</h3>

			<div class="alert alert-danger d-flex align-items-center gap-2" role="alert" aria-live="assertive">
				<i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
				<span>This invite has expired.</span>
			</div>

			<p class="text-muted text-center mb-0" style="font-size: 0.875rem;">
				Please contact your admin or the person who invited you to ask for a new invite.
			</p>
		</div>
	</div>

</section>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
