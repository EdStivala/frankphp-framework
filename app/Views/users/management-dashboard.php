<?php
$title = 'Users — ' . htmlspecialchars($tenant['name'] ?? '');
ob_start();
?>

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4 class="mb-0">Users</h4>
        <p class="text-muted mb-0" style="font-size:0.8125rem;"><?= htmlspecialchars($tenant['name'] ?? '') ?></p>
    </div>
</div>

<!-- KPI Stat Cards -->
<div class="row g-3 mb-4">

    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon--primary">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-value"><?= $totalUsers ?></div>
                <div class="stat-card-label">Total Users</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon--success">
                <i class="bi bi-activity"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-value"><?= $activePercent ?>%</div>
                <div class="stat-card-label">Active Users</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon--warning">
                <i class="bi bi-person-slash"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-value"><?= $neverLoggedIn ?></div>
                <div class="stat-card-label">Never Logged In</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card-icon stat-card-icon--danger">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-value"><?= $inactiveThirtyDays ?></div>
                <div class="stat-card-label">Inactive 30+ Days</div>
            </div>
        </div>
    </div>

</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header-modern">
        <h5><i class="bi bi-person me-2"></i>All Users</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($users)) : ?>
            <div class="text-center py-5 text-muted" style="font-size:0.875rem;">
                <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:0.75rem;opacity:0.35;"></i>
                No users found for this tenant.
            </div>
        <?php else : ?>
        <div class="table-responsive">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Last Login</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $row) : ?>
                    <tr>
                        <td class="col-email"><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <span class="role-badge role-badge--<?= htmlspecialchars($row['role']) ?>">
                                <?= htmlspecialchars($row['role']) ?>
                            </span>
                        </td>
                        <td class="col-date"><?= htmlspecialchars($row['joinedLabel']) ?></td>
                        <td class="<?= $row['hasLoggedIn'] ? 'col-date' : 'col-never' ?>">
                            <?= htmlspecialchars($row['lastLoginLabel']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_VIEWS_DIR . '/layouts/app-main.php';
?>
