<?php
$title = 'Create Account - FrankPHP';
ob_start();
?>

<section class="d-flex align-items-center justify-content-center vh-100">

	<div class="card shadow login-card">
		<div class="card-body p-4">

			<h3 class="text-center mb-4">
				<img
				src="/assets/images/logo_full.svg"
					style="width: 200px;"
					alt="FrankPHP new user sign up"
				/>
			</h3>

			<h4 class="text-center mb-2">Create your account</h4>

			<p class="text-muted text-center mb-4" style="font-size: 0.875rem;">
				Enter your email and choose a password. We'll send you a verification code to confirm your address.
			</p>

			<?php if (isset($error)): ?>
			<div class="alert alert-danger" role="alert">
				<?= htmlspecialchars($error) ?>
			</div>
			<?php endif; ?>

			<form id="signupForm" method="post" action="/signup">

				<!-- Email -->
				<div class="mb-3">
					<label for="email" class="form-label">Email Address</label>
					<input
						type="email"
						class="form-control"
						id="email"
						name="email"
						placeholder="name@example.com"
						value="<?= htmlspecialchars($email ?? '') ?>"
						required
						autofocus
					>
				</div>

				<!-- Password -->
				<div class="mb-3">
					<label for="password" class="form-label">Password</label>
					<div class="input-group">
						<input
							type="password"
							class="form-control"
							id="password"
							name="password"
							required
							minlength="8"
						>
						<span class="input-group-text password-toggle" onclick="togglePassword('password', 'iconPassword')">
							<i class="bi bi-eye-slash" id="iconPassword"></i>
						</span>
					</div>
					<small class="text-muted">At least 8 characters</small>
				</div>

				<!-- Confirm Password -->
				<div class="mb-4">
					<label for="password_confirm" class="form-label">Confirm Password</label>
					<div class="input-group">
						<input
							type="password"
							class="form-control"
							id="password_confirm"
							name="password_confirm"
							required
							minlength="8"
						>
						<span class="input-group-text password-toggle" onclick="togglePassword('password_confirm', 'iconConfirm')">
							<i class="bi bi-eye-slash" id="iconConfirm"></i>
						</span>
					</div>
				</div>

				<!-- Submit -->
				<button type="submit" class="btn btn-primary w-100 mb-3">Send Verification Code</button>

				<!-- Back to login -->
				<div class="text-center pt-3 border-top">
					<p class="mb-0 text-muted small">
						Already have an account?
						<a href="/login" class="text-decoration-none fw-bold">Sign in</a>
					</p>
				</div>

			</form>
		</div>
	</div>

	<script>
		function togglePassword(inputId, iconId) {
			const input = document.getElementById(inputId);
			const icon  = document.getElementById(iconId);
			if (input.type === 'password') {
				input.type = 'text';
				icon.classList.replace('bi-eye-slash', 'bi-eye');
			} else {
				input.type = 'password';
				icon.classList.replace('bi-eye', 'bi-eye-slash');
			}
		}

		// Client-side password match check before submission
		document.getElementById('signupForm').addEventListener('submit', function (e) {
			const pw  = document.getElementById('password').value;
			const pwc = document.getElementById('password_confirm').value;
			if (pw !== pwc) {
				e.preventDefault();
				alert('Passwords do not match. Please try again.');
			}
		});
	</script>

</section>


<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
