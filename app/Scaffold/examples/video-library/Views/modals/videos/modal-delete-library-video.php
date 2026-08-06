
<?php /* modal-delete-library-video.php — coach only */ ?>
<div class="modal fade" id="deleteLibraryVideoModal" tabindex="-1"
     aria-labelledby="deleteLibraryVideoModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="deleteLibraryVideoModalLabel">
					<i class="bi bi-trash me-2 text-danger"></i>Remove Video
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="mb-1">Remove
					<strong id="deleteLib_title"></strong> from the library?</p>
				<p class="text-muted" style="font-size:.85rem">This cannot be undone.</p>
			</div>
			<form id="deleteLib_form" method="post" action="">
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-danger">
						<i class="bi bi-trash"></i> Remove
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
