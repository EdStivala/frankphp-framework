<?php
$pageTag = 'Dashboard';
$title = 'Dashboard - ' . ($tenant['name'] ?? 'Tenant');
ob_start();
?>
<h2>
	Dashboard Skeleton: <?= htmlspecialchars($tenant['name']) ?>
</h2>
<h5>
    (Replace with your own custom dashboard / landing page)
</h5>
<div class="row">
	<div class="col-8">
		<div class="card mt-2">
			<h5 class="p-3">Card One - Main Body</h5>
		</div>

		
		<div class="card mt-5">	
			<h5 class="p-3" >Card Two - Main Body</h5>

		</div>
	</div>

	<div class="col-4">

		<!-- Task Summary Card -->
		<div class="card mb-3">
			<div class="card-header-modern">
				<h5>
					<i class="bi bi-check2-square me-2"></i>My Tasks</h5>
				<a href="/tenant/<?= $tenant['id'] ?>/tasks/create" class="btn btn-sm btn-brand-primary" style="padding: 0.25rem 0.75rem; font-size: 0.8125rem;">
					<i class="bi bi-plus-circle"></i> Add
				</a>
			</div>

			<!-- Task Filter Tabs -->
			<div class="task-filter-tabs" style="display: flex; gap: 0.25rem; padding: 0.75rem 1rem; border-bottom: 1px solid var(--grey-200); background: var(--grey-50);">
			
			
			<button class="filter-tab active" data-filter="today" style="flex: 1; padding: 0.375rem 0.5rem; font-size: 0.75rem; color: var(--grey-600); background: white; border: 1px solid var(--grey-200); border-radius: 6px; cursor: pointer; font-weight: 500;">
					Today
					<span class="task-count" style="display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 0.25rem; font-size: 0.65rem; background: var(--grey-200); color: var(--grey-700); border-radius: 9px; margin-left: 0.25rem;">
					<?= count($todayTasks ?? []) ?>
					</span>
				</button>
				
				
				<button class="filter-tab" data-filter="upcoming" style="flex: 1; padding: 0.375rem 0.5rem; font-size: 0.75rem; color: var(--grey-600); background: transparent; border: 1px solid transparent; border-radius: 6px; cursor: pointer; font-weight: 500;">
					Upcoming
					<span class="task-count" style="display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 0.25rem; font-size: 0.65rem; background: var(--brand-primary-light); color: var(--brand-primary); border-radius: 9px; margin-left: 0.25rem;">
					<?= count($upcomingTasks ?? []) ?>
					</span>
				</button>
				
				
				<button class="filter-tab" data-filter="overdue" style="flex: 1; padding: 0.375rem 0.5rem; font-size: 0.75rem; color: var(--grey-600); background: transparent; border: 1px solid transparent; border-radius: 6px; cursor: pointer; font-weight: 500;">
					Overdue
					<span class="task-count" style="display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 0.25rem; font-size: 0.65rem; background: var(--grey-200); color: var(--grey-700); border-radius: 9px; margin-left: 0.25rem;">
					<?= count($overdueTasks ?? []) ?>
					</span>
				</button>
				
			</div>

			<div class="card-body" style="padding: 1rem; max-height: 450px; overflow-y: auto;">

				<!-- Today's Tasks Section -->
				<div class="task-section" data-section="today">
					<?php
					if (!empty($todayTasks)) : ?>
					<?php
					foreach ($todayTasks as $task) :
						$priorityColors = ['', '#9CA3AF', '#6B7280', '#F59E0B', '#EF4444'];
						$priorityColor = $priorityColors[$task->priority ?? 2];
					?>
					<div class="task-item" style="display: flex; align-items: start; padding: 0.75rem; margin-bottom: 0.5rem; border: 1px solid #FEF3C7; border-radius: 6px; background: #FFFBEB; transition: all 0.15s ease; cursor: pointer;" onclick="window.location.href='/tenant/<?= $tenant['id'] ?>/tasks/update/<?= $task['id'] ?>'">
						<div style="flex-shrink: 0; margin-top: 0.125rem; margin-right: 0.75rem;">
							<input type="checkbox" style="width: 16px; height: 16px; cursor: pointer; accent-color: var(--brand-primary);" onclick="event.stopPropagation(); markTaskComplete(<?= $task['id'] ?>);">
						</div>
						<div style="flex: 1; min-width: 0;">
							<div style="display: flex; align-items: center; gap: 0.375rem; margin-bottom: 0.25rem; flex-wrap: wrap;">
								<span style="width: 6px; height: 6px; border-radius: 50%; background: <?= $priorityColor ?>; flex-shrink: 0;"></span>
								<a href="/tenant/<?= $tenant['id'] ?>/tasks/update/<?= $task['id'] ?>" style="font-size: 0.875rem; font-weight: 500; color: var(--grey-900); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($task['name']) ?></a>
							</div>
							<div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: var(--grey-500); flex-wrap: wrap;">
								<?php
								if (!empty($task->activity_time)) : ?>
								<span style="color: var(--color-warning); font-weight: 600;">
									<i class="bi bi-clock" style="font-size: 0.65rem;"></i>
									<?= date('g:i A', strtotime($task['activity_time'])) ?>
								</span>
								<?php
							endif; ?>
								<?php
								if (!empty($task->org_name)) : ?>
								<span>
									<i class="bi bi-building" style="font-size: 0.65rem;"></i>
									<?= htmlspecialchars(substr($task['org_name'], 0, 20)) ?>
								</span>
								<?php
							endif; ?>
							</div>
						</div>
					</div>
					<?php
				endforeach; ?>
					<?php else : ?>
					<div class="empty-state" style="color: var(--grey-400); font-size: 0.8125rem; padding: 2rem 1rem; text-align: center;">
						<i class="bi bi-calendar-x" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5; display: block;"></i>
						No tasks scheduled for today
					</div>
					<?php
				endif; ?>
				</div>

				<!-- Upcoming Tasks Section -->
				<div class="task-section" data-section="upcoming" style="display: none;">
					<?php
					if (!empty($upcomingTasks)) : ?>
					<?php
					foreach ($upcomingTasks as $task) :
						$priorityColors = ['', '#9CA3AF', '#6B7280', '#F59E0B', '#EF4444'];
						$priorityColor = $priorityColors[$task['priorty'] ?? 2];
						$daysUntil = floor((strtotime($task['activity_date']) - time()) / 86400);
					?>
					<div class="task-item" style="display: flex; align-items: start; padding: 0.75rem; margin-bottom: 0.5rem; border: 1px solid var(--grey-200); border-radius: 6px; background: white; transition: all 0.15s ease; cursor: pointer;" onclick="window.location.href='/tenant/<?= $tenant['id'] ?>/tasks/update/<?= $task['id'] ?>'">
						<div style="flex-shrink: 0; margin-top: 0.125rem; margin-right: 0.75rem;">
							<input type="checkbox" style="width: 16px; height: 16px; cursor: pointer; accent-color: var(--brand-primary);" onclick="event.stopPropagation(); markTaskComplete(<?= $task['id'] ?>);">
						</div>
						<div style="flex: 1; min-width: 0;">
							<div style="display: flex; align-items: center; gap: 0.375rem; margin-bottom: 0.25rem; flex-wrap: wrap;">
								<span style="width: 6px; height: 6px; border-radius: 50%; background: <?= $priorityColor ?>; flex-shrink: 0;"></span>
								<a href="/tenant/<?= $tenant['id'] ?>/tasks/update/<?= $task['id'] ?>" style="font-size: 0.875rem; font-weight: 500; color: var(--grey-900); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($task['name']) ?></a>
								<?php
								if (!empty($task['type'])) : ?>
								<span style="display: inline-flex; align-items: center; padding: 0.125rem 0.375rem; font-size: 0.65rem; border-radius: 3px; font-weight: 500; background: #EFF6FF; color: #1E40AF;">
								<?= htmlspecialchars($task['type']) ?>
								</span>
								<?php
							endif; ?>
							</div>
							<div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: var(--grey-500); flex-wrap: wrap;">
								<span>
									<i class="bi bi-calendar3" style="font-size: 0.65rem;"></i>
									<?= date('d M', strtotime($task['activity_date'])) ?>
									<?php
									if ($daysUntil == 0) : ?>
									<span style="color: var(--color-warning); font-weight: 600;">(Today)</span>
									<?php elseif ($daysUntil == 1) : ?>
									<span style="color: var(--color-success);">(Tomorrow)</span>
									<?php else : ?>
									(<?= $daysUntil ?> days)
									<?php
								endif; ?>
								</span>
								<?php
								if (!empty($task['org_name'])) : ?>
								<span>
									<i class="bi bi-building" style="font-size: 0.65rem;"></i>
									<?= htmlspecialchars(substr($task['org_name'], 0, 20)) ?>
								</span>
								<?php
							endif; ?>
							</div>
						</div>
					</div>
					<?php
				endforeach; ?>
					<?php else : ?>
					<div class="empty-state" style="color: var(--grey-400); font-size: 0.8125rem; padding: 2rem 1rem; text-align: center;">
						<i class="bi bi-calendar-check" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5; display: block;"></i>
						No upcoming tasks
					</div>
					<?php
				endif; ?>
				</div>

				<!-- Overdue Tasks Section -->
				<div class="task-section" data-section="overdue" style="display: none;">
					<?php
					if (!empty($overdueTasks)) : ?>
					<?php
					foreach ($overdueTasks as $task) :
						$priorityColors = ['', '#9CA3AF', '#6B7280', '#F59E0B', '#EF4444'];
						$priorityColor = $priorityColors[$task['priority'] ?? 2];
						$daysOverdue = floor((time() - strtotime($task['due_date'] ?? $task['activity_date'])) / 86400);
					?>
					<div class="task-item" style="display: flex; align-items: start; padding: 0.75rem; margin-bottom: 0.5rem; border: 1px solid #FEE2E2; border-radius: 6px; background: #FEF2F2; transition: all 0.15s ease; cursor: pointer;" onclick="window.location.href='/tenant/<?= $tenant['id'] ?>/tasks/update/<?= $task['id'] ?>'">
						<div style="flex-shrink: 0; margin-top: 0.125rem; margin-right: 0.75rem;">
							<input type="checkbox" style="width: 16px; height: 16px; cursor: pointer; accent-color: var(--brand-primary);" onclick="event.stopPropagation(); markTaskComplete(<?= $task['id'] ?>);">
						</div>
						<div style="flex: 1; min-width: 0;">
							<div style="display: flex; align-items: center; gap: 0.375rem; margin-bottom: 0.25rem; flex-wrap: wrap;">
								<span style="width: 6px; height: 6px; border-radius: 50%; background: <?= $priorityColor ?>; flex-shrink: 0;"></span>
								<a href="/tenant/<?= $tenant['id'] ?>/tasks/update/<?= $task['id'] ?>" style="font-size: 0.875rem; font-weight: 500; color: var(--grey-900); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($task['name']) ?></a>
							</div>
							<div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: var(--color-danger); font-weight: 600; flex-wrap: wrap;">
								<span>
									<i class="bi bi-exclamation-triangle-fill" style="font-size: 0.65rem;"></i>
									<?= $daysOverdue ?> day<?= $daysOverdue != 1 ? 's' : '' ?> overdue
								</span>
								<?php
								if (!empty($task['org_name'])) : ?>
								<span style="color: var(--grey-500); font-weight: 400;">
									<i class="bi bi-building" style="font-size: 0.65rem;"></i>
									<?= htmlspecialchars(substr($task['org_name'], 0, 20)) ?>
								</span>
								<?php
							endif; ?>
							</div>
						</div>
					</div>
					<?php
				endforeach; ?>
					<?php else : ?>
					<div class="empty-state" style="color: var(--grey-400); font-size: 0.8125rem; padding: 2rem 1rem; text-align: center;">
						<i class="bi bi-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5; display: block;"></i>
						No overdue tasks!
					</div>
					<?php
				endif; ?>
				</div>

			</div>

			<div style="padding: 0.875rem 1rem; border-top: 1px solid var(--grey-200); background: var(--grey-50);">
				<a href="/tenant/<?= $tenant['id'] ?>/tasks" style="font-size: 0.8125rem; color: var(--brand-primary); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.375rem; font-weight: 500;">
					View All Tasks
					<i class="bi bi-arrow-right"></i>
				</a>
			</div>
		</div>

	</div>
</div>

<?php
	$content = ob_get_clean();
	require APP_VIEWS_DIR . '/layouts/app-main.php';
?>