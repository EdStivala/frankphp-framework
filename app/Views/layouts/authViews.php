<?php
// Main App Layout for User Authentication and associated tasks : uses Bootstrap
?>
<!doctype html>
<html lang="en">
	<head>
		<?php include(APP_VIEWS_DIR . '/partials/gtm_head.html'); ?>
	
		<link rel="canonical" href="http://app,.bitfitter.me/" />

		<meta charset="utf-8">
		<meta http-equiv="x-ua-compatible" content="ie=edge">
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">

<meta name="description" content="FrankPHP is a pragmatic PHP scaffold for building multi-tenant, multi-user apps fast. Launch on shared hosting with FTP and MySQL, skip unnecessary infrastructure, and scale later when the product proves itself.">
		<meta name="ROBOTS" content="index, follow">
		<meta name="Author" content="Ed Stivala Limited">
		<title>FrankPHP | The AI-Native PHP Framework for multi-tenant SaaS Apps</title>

		<link href="../assets/vendor/bootstrap-5.3.6-dist/CSS/bootstrap.min.css" rel="stylesheet">
		<link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
		<link href="../assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

		<!-- Fonts -->
		<link href="https://fonts.googleapis.com" rel="preconnect">
		<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">

		<!-- CSS default for site then layering in each of the sections -->
		<!-- <link href="../assets/css/app-core.css" rel="stylesheet"> -->
		<link href="../assets/css/authViews.css" rel="stylesheet">


	</head>
	<body>
		<?php include(APP_VIEWS_DIR . '/partials/gtm_body.html'); ?>
		<!-- <?php include '../elements/mega-nav.php'; ?> -->
		
		<?= $content ?? '' ?>
		
	 	<!-- <?php include ('../elements/footer.html'); ?> -->

		<!-- Include POPPER for dropdown menu functionality -->
		<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"> </script>

		<script src="../assets/vendor/bootstrap-5.3.6-dist/JS/bootstrap.min.js"> </script>
		<script src="../assets/vendor/swiper/swiper-bundle.min.js"> </script>
		<script src="../assets/js/main.js"> </script>
	</body>
</html>

