<div class="nav-layout">	
	<nav id="sidebar" class="sidebar d-flex flex-column collapsed" aria-label="Sidebar navigation">
		<!-- 
		<div class="align-items-center justify-content-between mb-2 px-1">
			<div class=" align-items-center gap-2 sidebar-logo">
				<img
					src="\assets\images\revisio_logo.svg">
				</img>
			</div>
		</div>
		-->
		
		<ul class="nav flex-column" role="menu">
		
			<!--
			<li class="nav-item" role="none">
				<a 
					href="/tenant/<?= $tenant['id'] ?>/dashboard" 
					class="nav-link <?= $pageTag == 'Dashboard' ? 'active' : null ?>" 
					role="menuitem">
					<span class="side-icon">
						<i class="icon icon-home"></i>
					</span>
					<span class="side-label">Home</span>
				</a>
			</li>
			-->
			
			<li class="nav-item" data-menu="dashboard" role="none">
				<a
					href="/tenant/<?= $tenant['id'] ?>/dashboard"
					class="nav-link <?= $pageTag == 'Dashboard' ? 'active' : null ?>"
					role="menuitem"
					tabindex="0">
					<span class="side-icon">
						<i class="icon icon-dashboard"></i>
					</span>
					<span class="side-label">
						<span class="side-label-text">App Dashboard</span>
						<!--
						<span class="d-none d-lg-inline">
						<i class="bi bi-chevron-right small"></i>
						</span>
						-->
					</span>
				</a>
			</li>
			
			
			<hr>
			
			<li class="nav-item" data-menu="users" role="none">
				<a
					href="/tenant/<?= $tenant['id'] ?>/users"
					class="nav-link <?= $pageTag == 'Users' ? 'active' : null ?>"
					role="menuitem">
						<span class="side-icon">
							<i class="icon icon-users"></i>
						</span>
						<span class="side-label">
							<span class="side-label-text">Users</span>
						</span>
				</a>
			</li>

			<li class="nav-item" data-menu="tenant-settings" role="none">
				<a
					href="/tenant/<?= $tenant['id'] ?>/tenant-settings"
					class="nav-link <?= $pageTag == 'TenantSettings' ? 'active' : null ?>"
					role="menuitem">
						<span class="side-icon">
							<i class="bi bi-building-gear"></i>
						</span>
						<span class="side-label">
							<span class="side-label-text">Organisation Settings</span>
						</span>
				</a>
			</li>

			<li class="nav-item" data-menu="account-settings" role="none">
				<a
					href="/tenant/<?= $tenant['id'] ?>/account"
					class="nav-link <?= $pageTag == 'AccountSettings' ? 'active' : null ?>"
					role="menuitem">
						<span class="side-icon">
							<i class="bi bi-person-gear"></i>
						</span>
						<span class="side-label">
							<span class="side-label-text">Account</span>
						</span>
				</a>
			</li>

		</ul>

		<button id="collapseBtn" class="btn btn-sm sidebar-toggle" title="Collapse sidebar" aria-pressed="false">
			<i class="bi bi-chevron-left"></i>
		</button>
	</nav>
	
	<!-- Popouts for collapsed sidebar -->
	<div id="popout-organisations" class="popout" role="dialog" aria-hidden="true">
		<div class="fw-semibold mb-1">Organisations</div>
		<a href="#" class="submenu-item">All chats</a>
		<a href="https://bbc.co.uk" class="submenu-item">New Chat</a>
		<a href="#" class="submenu-item">Starred</a>
	</div>

	<div id="popout-people" class="popout" role="dialog" aria-hidden="true">
		<div class="fw-semibold mb-1">Peoplee</div>
		<a href="#" class="submenu-item">Discover</a>
		<a href="#" class="submenu-item">Templates</a>
		<a href="#" class="submenu-item">Collections</a>
	</div>
</div>