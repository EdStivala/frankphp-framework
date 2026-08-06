<?php /* modal-edit-library-video.php — coach only */ ?>
<div class="modal fade" id="editLibraryVideoModal" tabindex="-1"
     aria-labelledby="editLibraryVideoModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editLibraryVideoModalLabel">
					<i class="bi bi-pencil me-2" style="color:var(--brand-primary)"></i>Edit Library Video
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="editLib_form" method="post" action="">
				<input type="hidden" id="editLib_id" name="id">
				<div class="modal-body">
					<div class="mb-3">
						<label for="editLib_title" class="form-label">Title <span class="text-danger">*</span></label>
						<input type="text" class="form-control" id="editLib_title" name="title"
						required maxlength="200">
					</div>
					<div class="mb-3">
						<label for="editLib_url" class="form-label">YouTube URL <span class="text-danger">*</span></label>
						<input type="url" class="form-control" id="editLib_url" name="youtube_url" required>
					</div>
					<div class="mb-3">
						<label for="editLib_genre" class="form-label">Genre <span class="text-danger">*</span></label>
						<select class="form-select" id="editLib_genre" name="genre" required>
							<?php
							foreach ($genres as $g) : ?>
							<option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
							<?php
						endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label for="editLib_description" class="form-label">Description</label>
						<textarea class="form-control" id="editLib_description" name="description"
                                  rows="3" maxlength="500"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-brand-primary">
						<i class="bi bi-check-lg"></i> Save Changes
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
