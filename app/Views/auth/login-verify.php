<?php
// Enter the emailed 6-digit code (FrankPHP 3.0). Supplied by AuthController:
// $email, and optionally $error / $success.
$title = 'Enter your code - FrankPHP';
ob_start();
?>

<section class="d-flex align-items-center justify-content-center vh-100">

	<div class="card shadow login-card">
		<div class="card-body p-4">

			<h3 class="text-center mb-4">
				<img
					src="/assets/images/logo_full.svg"
					style="width: 200px;"
					alt="FrankPHP sign-in code"
				/>
			</h3>

			<!-- Icon -->
			<div class="text-center mb-3">
				<i class="bi bi-envelope-check text-primary" style="font-size: 2.5rem;"></i>
			</div>

			<h4 class="text-center mb-2">Check your email</h4>

			<p class="text-muted text-center mb-4" style="font-size: 0.875rem;">
				We sent a 6-digit code to <strong><?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?></strong>.
				Enter it below to continue.
			</p>

			<?php if (!empty($error)): ?>
			<div class="alert alert-danger" role="alert">
				<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
			</div>
			<?php endif; ?>

			<?php if (!empty($success)): ?>
			<div class="alert alert-info" role="alert">
				<?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
			</div>
			<?php endif; ?>

			<form id="verifyForm" method="post" action="/login/verify">

				<!-- CSRF Token -->
				<?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>

				<!-- 6-digit code -->
				<div class="mb-4">
					<label for="code" class="form-label">Code</label>
					<input
						type="text"
						class="form-control text-center"
						id="code"
						name="code"
						placeholder="000000"
						maxlength="6"
						inputmode="numeric"
						pattern="[0-9]{6}"
						autocomplete="one-time-code"
						required
						autofocus
					>
					<small class="text-muted">6-digit code &mdash; expires in 15 minutes</small>
				</div>

				<!-- Submit -->
				<button type="submit" id="verifySubmit" class="btn btn-primary w-100 mb-3">Continue</button>
			</form>

			<!-- Resend — its own form, NOT nested inside the verify form -->
			<form id="resendForm" method="post" action="/login/resend" class="text-center mb-2">
				<?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
				<button type="submit" class="btn btn-link btn-sm text-decoration-none p-0">
					Didn't receive a code? Send a new one
				</button>
			</form>

			<!-- Start over -->
			<div class="text-center">
				<a href="/login" class="text-decoration-none small text-muted">Use a different email</a>
			</div>

		</div>
	</div>

	<script>
		// Digits only — the person submits explicitly with the button (or Enter).
		document.getElementById('code').addEventListener('input', function () {
			this.value = this.value.replace(/\D/g, '');
		});

		// Submit-once guard — blocks Enter + click / double-click / autofill
		// submitting the form twice.
		(function () {
			const form   = document.getElementById('verifyForm');
			const button = document.getElementById('verifySubmit');
			let submitted = false;

			form.addEventListener('submit', function (e) {
				if (submitted) {
					e.preventDefault();
					return;
				}
				submitted = true;
				button.disabled = true;
				button.textContent = 'Checking…';
			});

			// Re-enable if the page is restored from the back/forward cache.
			window.addEventListener('pageshow', function (e) {
				if (e.persisted) {
					submitted = false;
					button.disabled = false;
					button.textContent = 'Continue';
				}
			});
		})();
	</script>

</section>


<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
