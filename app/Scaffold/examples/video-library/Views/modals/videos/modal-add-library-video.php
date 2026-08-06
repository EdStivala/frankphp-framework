<?php /* modal-add-library-video.php — coach only */ ?>
<div class="modal fade" id="addLibraryVideoModal" tabindex="-1"
     aria-labelledby="addLibraryVideoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLibraryVideoModalLabel">
                    <i class="bi bi-plus-circle me-2" style="color:var(--brand-primary)"></i>Add Video to Library
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="/tenant/<?= (int)$tenant['id'] ?>/videos/library/store">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="addLib_title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="addLib_title" name="title"
                               placeholder="e.g. Morning Power Yoga" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label for="addLib_url" class="form-label">YouTube URL <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="addLib_url" name="youtube_url"
                               placeholder="https://www.youtube.com/watch?v=..." required>
                        <div class="form-text">Paste any standard YouTube link (watch, youtu.be, or shorts).</div>
                    </div>
                    <div class="mb-3">
                        <label for="addLib_genre" class="form-label">Genre <span class="text-danger">*</span></label>
                        <select class="form-select" id="addLib_genre" name="genre" required>
                            <?php foreach ($genres as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addLib_description" class="form-label">Description</label>
                        <textarea class="form-control" id="addLib_description" name="description"
                                  rows="3" maxlength="500"
                                  placeholder="A short description of what this workout covers…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand-primary">
                        <i class="bi bi-plus-lg"></i> Add to Library
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
