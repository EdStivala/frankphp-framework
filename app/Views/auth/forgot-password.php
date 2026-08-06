<?php
$title = 'Forgot Password - FrankPHP';
ob_start();
?>

<section class="d-flex align-items-center justify-content-center vh-100">

	<div class="card shadow login-card">
		<div class="card-body p-4">
			<h3 class="text-center mb-4">
				<img
				src="/assets/images/logo_full.svg"
				style="width: 200px;"
				/>
			</h3>

			<h4 class="text-center mb-3">Reset Your Password</h4>

			<p class="text-muted text-center mb-4">
				Enter your email address and we'll send you instructions to reset your password.
			</p>

			<?php
			if (isset($error)) : ?>
			<div class="alert alert-danger" role="alert">
				<?= htmlspecialchars($error) ?>
			</div>
			<?php
		endif; ?>

			<form id="forgotPasswordForm" method="post" action="/forgot-password">
				<!-- Email Input -->
				<div class="mb-3">
					<label for="email" class="form-label">Email Address</label>
					<input
					type="email"
					class="form-control"
					id="email"
					name="email"
					placeholder="name@example.com"
					required
					autofocus
					>
				</div>

				<!-- Submit Button -->
				<button type="submit" class="btn btn-primary w-100 mb-3">Send Reset Link</button>

				<!-- Back to Login -->
				<div class="text-center">
					<a href="/login" class="text-decoration-none small">Back to Login</a>
				</div>
			</form>
		</div>
	</div>

</section>


<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>