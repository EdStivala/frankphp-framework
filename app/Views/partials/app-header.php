	<button id="mobileMenuBtn" class="btn btn-sm btn-ghost d-lg-none me-1" aria-label="Toggle menu">
		<i class="bi bi-list"></i></button>

	<div class="logo-text">
		<img
		src="/assets/images/logo_full.svg"
		/>
	</div>
		
	 <div class="dropdown d-none d-lg-block">
		<button class="btn btn-header-glass ms-5 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
			<i class="bi bi-house-gear me-2 opacity-75"></i>
		<?= htmlspecialchars($tenant['name']); ?>
		</button>
		<ul class="dropdown-menu dropdown-menu-end">
			<li>
				<a class="dropdown-item" href="#">
					<i class="bi bi-folder2-open"></i> Files</a>
			</li>
			<li>
				<a class="dropdown-item" href="/tenant/<?= (int) $tenant['id'] ?>/tenant-settings">
					<i class="bi bi-gear"></i> Settings</a>
			</li>
			
			<li>
				<a class="dropdown-item" href="#">
					<i class="bi bi-credit-card"></i> Subscription &amp; Billing</a>
			</li>
			<?php if (in_array($user['role'] ?? '', ['platform', 'owner'], true)) : ?>
			<li><hr class="dropdown-divider"></li>
			<li>
				<a class="dropdown-item" href="/tenant/<?= $tenant['id'] ?>/users">
					<i class="bi bi-people"></i> Manage Users</a>
			</li>
			<?php endif; ?>
		</ul>
	</div>

	<?php if (\Frank\Core\Session::supportAccess()) : ?>
	<?php // Support access (v3.0.0): a platform user logged in as this tenant's owner — centred in the red top bar ?>
	<form method="post" action="/tenant/<?= (int) $tenant['id'] ?>/support/stop"
		class="m-0 d-flex align-items-center gap-2 position-absolute start-50 translate-middle-x">
		<?php include APP_VIEWS_DIR . '/partials/csrf-field.php'; ?>
		<button type="submit" class="btn btn-header-glass">
			<i class="bi bi-box-arrow-left me-1"></i>Release Tenant
		</button>
		<span class="badge rounded-pill bg-white text-danger px-3 py-2"><i class="bi bi-eye me-1"></i>Acting as Tenant Owner</span>
	</form>
	<?php endif; ?>

	<div class="ms-auto d-flex align-items-center gap-2">
		<div class="dropdown">
			<button class="btn btn-header-glass dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
				<i class="bi bi-person"></i> <?= $user['name']; ?>
			</button>
			<ul class="dropdown-menu dropdown-menu-end">
				<li>
					<a class="dropdown-item" href="/tenant/<?= (int) $tenant['id'] ?>/account">
						<i class="bi bi-person-bounding-box"></i> Profile</a>
				</li>
				<li><hr class="dropdown-divider"></li>
				<li>
					<a class="dropdown-item text-danger" href="/logout">
						<i class="bi bi-box-arrow-right"></i> Sign out</a>
				</li>
			</ul>
		</div>
	</div>