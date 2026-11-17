<?php
// Invite welcome screen (FrankPHP 3.0). Opened from an invite link; no code
// is needed. Nothing is created until the button is pressed (mail scanners
// open links, they never press buttons). With terms on it asks for them as a
// normal sign-up does; the server enforces acceptance — the required
// attribute and disabled button are UX only.
// Supplied by AuthController: $email, $requireTerms, optional $error.
// Same card layout as login.php / login-terms.php. Provisional wording.
$title = 'Welcome - FrankPHP';
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

			<h4 class="text-center mb-2">Welcome aboard!</h4>

			<p class="text-muted text-center mb-4" style="font-size: 0.875rem;">
				<?php if ($requireTerms) : ?>
					You've been invited as <strong><?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?></strong>.
					Accept the Terms &amp; Conditions and your new account is ready.
				<?php else : ?>
					Great — your new account for <strong><?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?></strong> is waiting for you.
				<?php endif; ?>
			</p>

			<?php if (!empty($error)) : ?>
			<div class="alert alert-danger" role="alert">
				<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
			</div>
			<?php endif; ?>

			<form id="inviteForm" method="post" action="/invite/accept">

				<!-- CSRF Token -->
				<?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>

				<?php if ($requireTerms) : ?>
				<div class="form-check mb-4">
					<input
						type="checkbox"
						class="form-check-input"
						id="terms_accepted"
						name="terms_accepted"
						value="1"
						required
					>
					<label class="form-check-label small" for="terms_accepted">
						I have read and agree to the
						<a href="/terms" target="_blank" rel="noopener" class="text-decoration-none">Terms &amp; Conditions</a>.
					</label>
				</div>
				<?php endif; ?>

				<button type="submit" id="inviteSubmit" class="btn btn-primary w-100">Let me see</button>

			</form>
		</div>
	</div>

	<?php if ($requireTerms) : ?>
	<script>
		// Progressive enhancement: the button stays disabled until terms are ticked.
		(function () {
			const terms  = document.getElementById('terms_accepted');
			const submit = document.getElementById('inviteSubmit');
			const sync   = () => { submit.disabled = !terms.checked; };
			terms.addEventListener('change', sync);
			sync();
		})();
	</script>
	<?php endif; ?>

</section>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
