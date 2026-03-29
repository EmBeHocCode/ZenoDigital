<?php $flashes = get_flash(); ?>
<?php if ($flashes): ?>
    <div class="container mt-3">
        <?php foreach ($flashes as $flashMessage): ?>
            <div class="alert alert-<?= e($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($flashMessage['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
