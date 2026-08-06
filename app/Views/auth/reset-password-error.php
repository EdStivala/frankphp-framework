<?php
$title = 'Reset Link Invalid - FrankPHP';
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
			
			<div class="text-center mb-4">
				<i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
			</div>
			
			<h4 class="text-center mb-3">Reset Link Invalid</h4>
			
			<div class="alert alert-warning" role="alert">
				<?= htmlspecialchars($message ?? 'This password reset link is invalid or has expired.') ?>
			</div>
			
			<p class="text-muted text-center mb-4">
				Password reset links expire after 1 hour for security reasons. 
				If you still need to reset your password, please request a new link.
			</p>
			
			<div class="d-grid gap-2">
				<a href="/forgot-password" class="btn btn-primary">Request New Reset Link</a>
				<a href="/login" class="btn btn-outline-secondary">Return to Login</a>
			</div>
		</div>
	</div>

</section>


<?php
$content = ob_get_clean(); 
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
