<?php
$title = 'Verify Your Email - FrankPHP';
ob_start();
?>

<section class="d-flex align-items-center justify-content-center vh-100">

	<div class="card shadow login-card">
		<div class="card-body p-4">

			<h3 class="text-center mb-4">
				<img
					src="/assets/images/logo_full.svg"
					style="width: 200px;"
					alt="FrankPHP new user sign up verification"
				/>
			</h3>

			<!-- Icon -->
			<div class="text-center mb-3">
				<i class="bi bi-envelope-check text-primary" style="font-size: 2.5rem;"></i>
			</div>

			<h4 class="text-center mb-2">Check your email</h4>

			<p class="text-muted text-center mb-4" style="font-size: 0.875rem;">
				We sent a 6-digit code to <strong><?= htmlspecialchars($email ?? '') ?></strong>.
				Enter it below to confirm your address and create your account.
			</p>

			<?php if (isset($error)): ?>
			<div class="alert alert-danger" role="alert">
				<?= htmlspecialchars($error) ?>
			</div>
			<?php endif; ?>

			<?php if (isset($success)): ?>
			<div class="alert alert-info" role="alert">
				<?= htmlspecialchars($success) ?>
			</div>
			<?php endif; ?>

			<form id="verifyForm" method="post" action="/signup/verify">

				<!-- 6-digit code -->
				<div class="mb-4">
					<label for="code" class="form-label">Verification Code</label>
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
				<button type="submit" class="btn btn-primary w-100 mb-3">Verify &amp; Create Account</button>

				<!-- Resend -->
				<div class="text-center mb-2">
					<form method="post" action="/signup/resend" class="d-inline">
						<button type="submit" class="btn btn-link btn-sm text-decoration-none p-0">
							Didn't receive a code? Resend
						</button>
					</form>
				</div>

				<!-- Start over -->
				<div class="text-center">
					<a href="/signup" class="text-decoration-none small text-muted">Use a different email</a>
				</div>

			</form>
		</div>
	</div>

	<script>
		// Auto-advance: submit when 6 digits are entered
		document.getElementById('code').addEventListener('input', function () {
			const val = this.value.replace(/\D/g, '');
			this.value = val;
			if (val.length === 6) {
				document.getElementById('verifyForm').submit();
			}
		});
	</script>

</section>


<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
