<?php foreach (['message' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $type): ?>
    <?php if (session($key) !== null): ?>
        <div class="alert alert-<?= $type ?>" role="alert"><?= esc(session($key)) ?></div>
    <?php endif ?>
<?php endforeach ?>

<?php if (session('errors') !== null): ?>
    <div class="alert alert-danger" role="alert">
        <?php foreach ((array) session('errors') as $error): ?>
            <div><?= esc($error) ?></div>
        <?php endforeach ?>
    </div>
<?php endif ?>
