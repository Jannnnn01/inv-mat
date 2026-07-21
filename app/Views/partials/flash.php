<?php
$flashTypes = [
    'message' => ['class' => 'success', 'label' => 'Operación completada'],
    'info'    => ['class' => 'info', 'label' => 'Información'],
    'warning' => ['class' => 'warning', 'label' => 'Atención'],
    'error'   => ['class' => 'danger', 'label' => 'Ocurrió un problema'],
];
?>

<div class="app-flash-stack" aria-live="polite" aria-atomic="true">
    <?php foreach ($flashTypes as $key => $alert): ?>
        <?php if (session($key) !== null): ?>
            <div class="alert alert-<?= $alert['class'] ?> app-alert" role="<?= $key === 'error' ? 'alert' : 'status' ?>">
                <span class="app-alert-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <circle cx="12" cy="12" r="9"></circle>
                        <?php if ($key === 'message'): ?>
                            <path d="m8.5 12 2.2 2.2 4.8-5"></path>
                        <?php else: ?>
                            <path d="M12 8v4.5M12 16h.01"></path>
                        <?php endif ?>
                    </svg>
                </span>
                <span>
                    <strong class="d-block"><?= esc($alert['label']) ?></strong>
                    <?= esc(session($key)) ?>
                </span>
            </div>
        <?php endif ?>
    <?php endforeach ?>

    <?php if (session('errors') !== null): ?>
        <div class="alert alert-danger app-alert" role="alert">
            <span class="app-alert-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 8v4.5M12 16h.01"></path>
                </svg>
            </span>
            <div>
                <strong class="d-block">Revisa la información ingresada</strong>
                <ul class="mb-0 ps-3">
                    <?php foreach ((array) session('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>
    <?php endif ?>
</div>
