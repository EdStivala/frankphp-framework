<?php
// One screen for sign-in and sign-up (FrankPHP 3.0). The person enters an
// email address and is emailed a 6-digit code. The CSRF field comes from
// partials/csrf-field.php.
$title = 'Sign in to FrankPHP';

ob_start();
?>

<section class="d-flex align-items-center justify-content-center vh-100">

	<div class="card shadow login-card">
		<div class="card-body p-4">
			<h3 class="text-center mb-4">
				<img
				src="/assets/images/logo_full.svg"
				style="width: 200px;"
				alt="FrankPHP Login Panel"
				/>
			</h3>

			<p class="text-muted text-center mb-4" style="font-size: 0.875rem;">
				Enter your email address and we'll send you a 6-digit code. No password needed.
			</p>

			<?php
			if (!empty($error)) : ?>
			<div class="alert alert-danger d-flex align-items-center gap-2" role="alert" aria-live="assertive">
				<i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
				<span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
			</div>
			<?php
		endif; ?>

			<form id="loginForm" method="post" action="/login">

				<!-- CSRF Token -->
				<?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>

				<!-- Email Input -->
				<div class="mb-3">
					<label for="email" class="form-label">Email Address</label>
					<input
					type="email"
					class="form-control <?= !empty($error) ? 'is-invalid' : '' ?>"
					id="email"
					name="email"
					placeholder="name@example.com"
					value="<?= htmlspecialchars($submittedEmail ?? '', ENT_QUOTES, 'UTF-8') ?>"
					autocomplete="email"
					required
					autofocus
					>
				</div>

				<!-- Submit Button -->
				<button type="submit" class="btn btn-primary w-100">Email me a code</button>

			</form>

			<!-- Google / Microsoft sign-in (sprint 4, provisional: buttons only, not wired yet) -->
			<div class="d-flex align-items-center my-3">
				<hr class="flex-grow-1">
				<span class="px-2 text-muted" style="font-size: 0.875rem;">or</span>
				<hr class="flex-grow-1">
			</div>

			<!-- A provider whose keys are not set ($config['oauth']) shows its button disabled. -->
			<?php if (!empty($googleEnabled)) : ?>
			<a href="/login/google" class="btn btn-outline-secondary w-100 mb-2">
				<i class="bi bi-google me-2"></i>Continue with Google
			</a>
			<?php else : ?>
			<button type="button" class="btn btn-outline-secondary w-100 mb-2" disabled>
				<i class="bi bi-google me-2"></i>Continue with Google
			</button>
			<?php endif; ?>
			<button type="button" class="btn btn-outline-secondary w-100" <?= empty($microsoftEnabled) ? 'disabled' : '' ?>>
				<i class="bi bi-microsoft me-2"></i>Continue with Microsoft
			</button>
		</div>
	</div>

</section>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
