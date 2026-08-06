<?php
$title = 'Login to FrankPHP';

// Generate CSRF token if not already set
if (session_status() === PHP_SESSION_NONE)
	session_start();
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

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
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

				<!-- Hidden next redirect -->
				<?php
				if (!empty($next) && $next !== '/') : ?>
				<input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
				<?php
			endif; ?>

				<!-- Email Input -->
				<div class="mb-3">
					<label for="userEmail" class="form-label">Email Address</label>
					<input
					type="email"
					class="form-control <?= !empty($error) ? 'is-invalid' : '' ?>"
					id="userEmail"
					name="userEmail"
					placeholder="name@example.com"
					value="<?= htmlspecialchars($submittedEmail ?? '', ENT_QUOTES, 'UTF-8') ?>"
					autocomplete="email"
					required
					>
				</div>

				<!-- Password Input with Toggle -->
				<div class="mb-3">
					<label for="userPassword" class="form-label">Password</label>
					<div class="input-group">
						<input
						type="password"
						class="form-control <?= !empty($error) ? 'is-invalid' : '' ?>"
						id="userPassword"
						name="userPassword"
						autocomplete="current-password"
						required
						>
						<span class="input-group-text password-toggle" role="button" aria-label="Toggle password visibility" onclick="togglePassword()">
							<i class="bi bi-eye-slash" id="toggleIcon" aria-hidden="true"></i>
						</span>
					</div>
				</div>

				<!-- Action Links -->
				<div class="d-flex justify-content-between mb-3 small">
					<a href="/forgot-password" class="text-decoration-none">Forgot password?</a>
				</div>

				<!-- Submit Button -->
				<button type="submit" class="btn btn-primary w-100">Sign In</button>
				
				<div class="text-center pt-3 border-top">
					<p class="mb-0 text-muted small">
						New to FrankPHP?
						<a href="/signup" class="text-decoration-none fw-bold">Create a free account</a>
					</p>
				</div>
				
			</form>
		</div>
	</div>

	<script>
		function togglePassword()
		{
			const passwordInput = document.getElementById('userPassword');
			const toggleIcon = document.getElementById('toggleIcon');

			if (passwordInput.type === 'password') {
				passwordInput.type = 'text';
				toggleIcon.classList.replace('bi-eye-slash', 'bi-eye');
			} else {
				passwordInput.type = 'password';
				toggleIcon.classList.replace('bi-eye', 'bi-eye-slash');
			}
		}
	</script>
</section>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>