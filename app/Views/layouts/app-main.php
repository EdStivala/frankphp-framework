<?php 
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

?>
<!doctype html>
<html lang="en">
	<head>
		<?php include(APP_VIEWS_DIR . '/partials/gtm_head.html'); ?>
		<title><?php echo isset($title) ? $title . " | Page" : "FrankPHP"; ?></title>
		<meta name="description" content="FrankPHP is a pragmatic PHP scaffold for building multi-tenant, multi-user apps fast. Launch on shared hosting with FTP and MySQL, skip unnecessary infrastructure, and scale later when the product proves itself.">
		<meta name="ROBOTS" content="index, follow">
		<meta name="Author" content="Ed Stivala Limited">
	
		<!-- Future enhancment NB: php vars will need customising
		<script>
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			'user_status': '<?php echo $isLoggedIn ? "logged_in" : "guest"; ?>',
			'account_type': '<?php echo $userPlan; ?>', // e.g., "Premium" or "Free"
			'page_category': '<?php echo $currentSection; ?>' // e.g., "Analytics" or "Billing"
		});
		</script>
		-->

		
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width,initial-scale=1" />

		<!-- Bootstrap 5 CSS (CDN) -->
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"> 
		<!-- Bootstrap Icons -->
		<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
		
		<!-- Syncfusion Bootstrap 5 Theme (Essential for e-danger color) -->
		<link href="https://cdn.syncfusion.com/ej2/32.1.19/bootstrap5.css" rel="stylesheet">
		<!-- Syncfusion JS Component -->
		<script src="https://cdn.syncfusion.com/ej2/32.1.19/dist/ej2.min.js" type="text/javascript"> </script>
		
		
		<!-- Local CSS Customisation -->
		<link href="/assets/css/app-core.css" type="text/css" rel="stylesheet">
		<link href="/assets/css/app-nav.css" type="text/css" rel="stylesheet">
		<link href="/assets/css/app-header.css" type="text/css" rel="stylesheet">
		<link href="/assets/css/app-icons.css" type="text/css" rel="stylesheet">
		<link href="/assets/css/app-pages-users.css" type="text/css" rel="stylesheet">
		<link href="/assets/css/app-pages-settings.css" type="text/css" rel="stylesheet">

		<!-- Extra head assets requested by the current view/controller — e.g. a
		     feature-specific stylesheet. The layout has no built-in knowledge of
		     any individual feature's CSS; pass it explicitly via 'headExtra'. -->
		<?php foreach (($headExtra ?? []) as $extraAsset) : ?>
		<link href="<?= htmlspecialchars($extraAsset) ?>" type="text/css" rel="stylesheet">
		<?php endforeach; ?>
		
		<!-- Global SF formaters and licence -->
		<!----
		APP_CONFIG: server-injected JS configuration block.

		Keys placed here are intentionally visible to the browser —
		this is correct behaviour for a Syncfusion licence key.
		The benefit of routing it through .env is that the key is
		removed from source control and from your .js files.
		-->
		<script>
		const APP_CONFIG = {
			syncfusionKey: "<?= htmlspecialchars($config['frontend']['syncfusion_key']) ?>"
		};
		</script>
		<!-- Syncfusion registration — reads from APP_CONFIG, not a hardcoded string -->
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			if (typeof ej !== 'undefined' && APP_CONFIG.syncfusionKey) {
				ej.base.registerLicense(APP_CONFIG.syncfusionKey);
			}
		});
		</script>
		
		<script src="/assets/js/sf/ui-field-masks.js"> </script>
		
	</head>
	<body class="">
		<?php include(APP_VIEWS_DIR . '/partials/gtm_body.html'); ?>
		<header class="topbar">
			<?php include(APP_VIEWS_DIR . '/partials/app-header.php'); ?>
		</header>
		
		<main class="mainBody d-flex">
			
				<!-- Navigation -->
				<?php include(APP_VIEWS_DIR . '/partials/app-nav.php'); ?>	
				
				<!-- Dynamic Page Contents -->
				<div class="page-content-area flex-grow-1">
					<div class="container-fluid py-3" id="app-container">
						<!-- Error and information messages -->
						<?php if (!empty($flash ?? null)) : ?>
							<div class="alert alert-info">
							<?= htmlspecialchars($flash) ?>
							</div>
						<?php endif; ?>
				
						<!-- Individual Page functionality -->							
						<?= $content ?? '' ?>
					</div>			
				</div>
				
		</main>
		
	<!-- Bootstrap JS and dependent Popper -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"> </script>
	
	<script src="/assets/js/this-site-nav.js"></script>
	
	</body>
</html>
