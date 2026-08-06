<?php /* modal-video-player.php */ ?>
<!-- ════════════════════════════════════════════
     VIDEO PLAYER MODAL
════════════════════════════════════════════ -->
<div class="modal fade" id="videoPlayerModal" tabindex="-1"
     aria-labelledby="videoPlayerTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content video-player-modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="videoPlayerTitle">Watch Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="video-player-wrapper">
                    <iframe id="videoPlayerIframe"
                            src=""
                            title="YouTube video player"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen>
                    </iframe>
                </div>
            </div>
        </div>
    </div>
</div>
