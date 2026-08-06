<?php
$title = 'Reset Password - ReVisio360';
ob_start();
?>

<section class="d-flex align-items-center justify-content-center vh-100">

	<div class="card shadow login-card">
		<div class="card-body p-4">
			<h3 class="text-center mb-4">
				<img
					src="assets\images\bf_logo_full.svg"
					style="width: 200px;"
				/>
			</h3>
			
			<h4 class="text-center mb-3">Create New Password</h4>
			
			<p class="text-muted text-center mb-4">
				Enter a new password for <strong><?= htmlspecialchars($email ?? '') ?></strong>
			</p>
			
			<?php if (isset($error)): ?>
			<div class="alert alert-danger" role="alert">
				<?= htmlspecialchars($error) ?>
			</div>
			<?php endif; ?>
			
			<form id="resetPasswordForm" method="post" action="/reset-password">
				<!-- Hidden Token Field -->
				<input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">
				
				<!-- New Password Input with Toggle -->
				<div class="mb-3">
					<label for="password" class="form-label">New Password</label>
					<div class="input-group">
						<input 
							type="password" 
							class="form-control" 
							id="password" 
							name="password" 
							required
							minlength="8"
							autofocus
						>
						<span class="input-group-text password-toggle" onclick="togglePassword('password', 'toggleIconPassword')">
							<i class="bi bi-eye-slash" id="toggleIconPassword"></i>
						</span>
					</div>
					<small class="text-muted">Must be at least 8 characters long</small>
				</div>

				<!-- Confirm Password Input with Toggle -->
				<div class="mb-3">
					<label for="password_confirm" class="form-label">Confirm New Password</label>
					<div class="input-group">
						<input 
							type="password" 
							class="form-control" 
							id="password_confirm" 
							name="password_confirm" 
							required
							minlength="8"
						>
						<span class="input-group-text password-toggle" onclick="togglePassword('password_confirm', 'toggleIconConfirm')">
							<i class="bi bi-eye-slash" id="toggleIconConfirm"></i>
						</span>
					</div>
				</div>

				<!-- Submit Button -->
				<button type="submit" class="btn btn-primary w-100 mb-3">Reset Password</button>
				
				<!-- Back to Login -->
				<div class="text-center">
					<a href="/login" class="text-decoration-none small">Back to Login</a>
				</div>
			</form>
		</div>
	</div>

	<!-- JavaScript for Password Toggle -->
	<script>
		function togglePassword(inputId, iconId)
		{
			const passwordInput = document.getElementById(inputId);
			const toggleIcon = document.getElementById(iconId);

			if (passwordInput.type === 'password') {
				passwordInput.type = 'text';
				toggleIcon.classList.replace('bi-eye-slash', 'bi-eye');
			} else {
				passwordInput.type = 'password';
				toggleIcon.classList.replace('bi-eye', 'bi-eye-slash');
			}
		}
		
		// Client-side password match validation
		document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
			const password = document.getElementById('password').value;
			const confirm = document.getElementById('password_confirm').value;
			
			if (password !== confirm) {
				e.preventDefault();
				alert('Passwords do not match. Please try again.');
				return false;
			}
		});
	</script>
</section>


<?php
$content = ob_get_clean(); 
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
