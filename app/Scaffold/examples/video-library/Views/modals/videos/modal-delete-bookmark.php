<?php /* modal-delete-bookmark.php */ ?>
<div class="modal fade" id="deleteBookmarkModal" tabindex="-1"
     aria-labelledby="deleteBookmarkModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="deleteBookmarkModalLabel">
					<i class="bi bi-trash me-2 text-danger"></i>Delete Bookmark
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="mb-1">Delete
					<strong id="deleteBk_title"></strong>?</p>
				<p class="text-muted" style="font-size:.85rem">This cannot be undone.</p>
			</div>
			<form id="deleteBk_form" method="post" action="">
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-danger">
						<i class="bi bi-trash"></i> Delete
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
