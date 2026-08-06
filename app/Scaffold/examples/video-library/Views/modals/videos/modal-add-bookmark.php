<?php /* modal-add-bookmark.php */ ?>
<div class="modal fade" id="addBookmarkModal" tabindex="-1"
     aria-labelledby="addBookmarkModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="addBookmarkModalLabel">
					<i class="bi bi-bookmark-plus me-2" style="color:var(--brand-primary)"></i>Add Bookmark
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form method="post" action="/tenant/<?= (int)$tenant['id'] ?>/videos/bookmarks/store">
				<div class="modal-body">
					<p class="text-muted mb-3" style="font-size:.85rem">
						<i class="bi bi-lock me-1"></i> Bookmarks are private — only you can see them.
					</p>
					<div class="mb-3">
						<label for="addBk_title" class="form-label">Title <span class="text-danger">*</span></label>
						<input type="text" class="form-control" id="addBk_title" name="title"
						placeholder="e.g. My Favourite HIIT Session" required maxlength="200">
					</div>
					<div class="mb-3">
						<label for="addBk_url" class="form-label">YouTube URL <span class="text-danger">*</span></label>
						<input type="url" class="form-control" id="addBk_url" name="youtube_url"
						placeholder="https://www.youtube.com/watch?v=..." required>
						<div class="form-text">Paste any standard YouTube link.</div>
					</div>
					<div class="mb-3">
						<label for="addBk_genre" class="form-label">Genre <span class="text-danger">*</span></label>
						<select class="form-select" id="addBk_genre" name="genre" required>
							<?php
							foreach ($genres as $g) : ?>
							<option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
							<?php
						endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label for="addBk_description" class="form-label">Notes</label>
						<textarea class="form-control" id="addBk_description" name="description"
                                  rows="3" maxlength="500"
                                  placeholder="Why do you love this workout?"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-brand-primary">
						<i class="bi bi-bookmark-check"></i> Save Bookmark
					</button>
				</div>
			</form>
		</div>
	</div>
</div>