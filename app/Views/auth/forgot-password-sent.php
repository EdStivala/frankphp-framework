<?php
$title = 'Check Your Email - ReVisio360';
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
			
			<div class="text-center mb-4">
				<i class="bi bi-envelope-check text-primary" style="font-size: 3rem;"></i>
			</div>
			
			<h4 class="text-center mb-3">Check Your Email</h4>
			
			<p class="text-muted text-center mb-4">
				If an account exists for <strong><?= htmlspecialchars($email ?? '') ?></strong>, 
				you will receive password reset instructions shortly.
			</p>
			
			<div class="alert alert-info" role="alert">
				<small>
					<strong>Didn't receive an email?</strong><br>
					Check your spam folder or try requesting another reset link.
				</small>
			</div>
			
			<div class="text-center">
				<a href="/login" class="btn btn-outline-primary">Return to Login</a>
			</div>
		</div>
	</div>

</section>


<?php
$content = ob_get_clean(); 
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
