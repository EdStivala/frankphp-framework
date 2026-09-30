<?php
// PLACEHOLDER — replace with your application's real Terms & Conditions
// before going live. Linked from auth/signup.php (FrankPHP 2.1+).
// Bump $config['signup']['terms_version'] whenever the content changes.
$title = 'Terms & Conditions - FrankPHP';
ob_start();
?>

<section class="d-flex align-items-center justify-content-center min-vh-100 py-5">

	<div class="card shadow" style="max-width: 720px; width: 100%;">
		<div class="card-body p-4">

			<h3 class="text-center mb-4">
				<img
					src="/assets/images/logo_full.svg"
					style="width: 200px;"
					alt="FrankPHP"
				/>
			</h3>

			<h4 class="mb-3">Terms &amp; Conditions</h4>

			<div class="alert alert-warning" role="alert">
				<strong>Placeholder.</strong> This page ships with the FrankPHP starter app.
				Replace <code>app/Views/auth/terms.php</code> with your own Terms &amp; Conditions
				before accepting real signups.
			</div>

			<p class="text-muted small mb-0">
				Terms version: <?= htmlspecialchars((string) ($termsVersion ?? 'not set')) ?>
			</p>

		</div>
	</div>

</section>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
