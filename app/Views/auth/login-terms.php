<?php
// Terms & Conditions step (FrankPHP 3.0). Only shown when
// $config['signup']['require_terms'] is on, to an email address that has just
// verified its code and has no account yet. The server enforces acceptance —
// the required attribute and disabled button below are UX only. Must post
// terms_accepted=1. Supplied by AuthController: $csrfToken, $email, optional $error.
$title = 'Terms & Conditions - FrankPHP';
ob_start();
?>

<section class="d-flex align-items-center justify-content-center vh-100">

	<div class="card shadow login-card">
		<div class="card-body p-4">

			<h3 class="text-center mb-4">
				<img
					src="/assets/images/logo_full.svg"
					style="width: 200px;"
					alt="FrankPHP terms and conditions"
				/>
			</h3>

			<h4 class="text-center mb-2">One last step</h4>

			<p class="text-muted text-center mb-4" style="font-size: 0.875rem;">
				<strong><?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?></strong> is verified.
				Please accept the Terms &amp; Conditions to create your account.
			</p>

			<?php if (!empty($error)): ?>
			<div class="alert alert-danger" role="alert">
				<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
			</div>
			<?php endif; ?>

			<form id="termsForm" method="post" action="/login/terms">

				<!-- CSRF Token -->
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

				<div class="form-check mb-4">
					<input
						type="checkbox"
						class="form-check-input"
						id="terms_accepted"
						name="terms_accepted"
						value="1"
						required
					>
					<label class="form-check-label small" for="terms_accepted">
						I have read and agree to the
						<a href="/terms" target="_blank" rel="noopener" class="text-decoration-none">Terms &amp; Conditions</a>.
					</label>
				</div>

				<button type="submit" id="termsSubmit" class="btn btn-primary w-100 mb-3">Continue</button>

				<div class="text-center">
					<a href="/login" class="text-decoration-none small text-muted">Start again</a>
				</div>

			</form>
		</div>
	</div>

	<script>
		// Progressive enhancement: submit stays disabled until terms are ticked.
		(function () {
			const terms  = document.getElementById('terms_accepted');
			const submit = document.getElementById('termsSubmit');
			const sync   = () => { submit.disabled = !terms.checked; };
			terms.addEventListener('change', sync);
			sync();
		})();
	</script>

</section>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/authViews.php';
?>
