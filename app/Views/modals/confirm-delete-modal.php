<?php
/**
* Confirm Delete Modal
*
* Reusable modal component for confirming destructive delete actions.
* Follows the quick-add-modal design system defined in app-modals.css.
*
* Required Variables:
* - $deleteLabel      : Short entity label shown in the title, e.g. 'Organization', 'Contact'
* - $deleteName       : Display name of the record being deleted, e.g. $organization->name
* - $deleteHref       : Full URL for the confirmed delete action, e.g. "/tenant/1/organizations/delete/42"
*
* Optional Variables:
* - $deleteWarning    : Override the secondary warning sentence. Defaults to a generic data-loss message.
* - $deleteModalId    : Override the modal HTML id. Defaults to 'confirmDeleteModal'.
*                       Override when multiple delete modals appear on one page.
*
* Usage Example:
* <?php
* $deleteLabel   = 'Organization';
* $deleteName    = $organization->name;
* $deleteHref    = "/tenant/{$tenant['id']}/organizations/delete/{$organization->id}";
* include APP_VIEWS_DIR . '/modals/confirm-delete.php';
* ?>
*
* Trigger Button Example:
* <button type="button" class="btn btn-outline-danger"
*         data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
*     <i class="bi bi-trash3"></i> Delete <?= htmlspecialchars($deleteLabel) ?>
* </button>
*/

$deleteModalId = $deleteModalId ?? 'confirmDeleteModal';
$deleteWarning = $deleteWarning ?? 'This action cannot be undone and will permanently remove all associated data.';
?>

<!-- Confirm Delete Modal -->
<div class="modal fade quick-add-modal" id="<?= $deleteModalId ?>" tabindex="-1"
	aria-labelledby="<?= $deleteModalId ?>Label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">

			<!-- Modal Header -->
			<div class="modal-header delete-modal-header">
				<div class="delete-modal-icon">
					<i class="bi bi-exclamation-triangle-fill text-danger"></i>
				</div>
				<h5 class="modal-title" id="<?= $deleteModalId ?>Label">
					Delete <?= htmlspecialchars($deleteLabel) ?>?
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>

			<!-- Modal Body -->
			<div class="modal-body">
				<p>Are you sure you want to delete
					<strong><?= htmlspecialchars($deleteName) ?></strong>?
				</p>
				<p class="text-muted mb-0"><?= htmlspecialchars($deleteWarning) ?></p>
			</div>

			<!-- Modal Footer -->
			<div class="modal-footer justify-content-end">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
					Cancel
				</button>
				<a href="<?= htmlspecialchars($deleteHref) ?>" class="btn btn-danger">
					<i class="bi bi-trash3-fill"></i> Delete <?= htmlspecialchars($deleteLabel) ?>
				</a>
			</div>

		</div>
	</div>
</div>