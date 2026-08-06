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
				<a class="dropdown-item" href="#">
					<i class="bi bi-gear"></i> Settings</a>
			</li>
			
			<li>
				<a class="dropdown-item" href="#">
					<i class="bi bi-credit-card"></i> Subscription &amp; Billing</a>
			</li>
			<li><hr class="dropdown-divider"></li>
			<li>
				<a class="dropdown-item" href="/tenant/<?= $tenant['id'] ?>/users">
					<i class="bi bi-people"></i> Manage Users</a>
			</li>
		</ul>
	</div>

	<div class="ms-auto d-flex align-items-center gap-2">
		<div class="dropdown">
			<button class="btn btn-header-glass dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
				<i class="bi bi-person"></i> <?= $user['name']; ?>
			</button>
			<ul class="dropdown-menu dropdown-menu-end">
				<li>
					<a class="dropdown-item" href="#">
						<i class="bi bi-person-bounding-box"></i> Profile</a>
				</li>
				<li>
					<a class="dropdown-item" href="#">
						<i class="bi bi-sliders"></i> Account</a>
				</li>
				<li><hr class="dropdown-divider"></li>
				<li>
					<a class="dropdown-item text-danger" href="/logout">
						<i class="bi bi-box-arrow-right"></i> Sign out</a>
				</li>
			</ul>
		</div>
	</div>