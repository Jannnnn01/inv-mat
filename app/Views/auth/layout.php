<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Acceso al sistema institucional de inventario">
    <title><?= esc($this->renderSection('title')) ?> | Inventario</title>
    <link rel="stylesheet" href="<?= base_url('assets/build/app.css') ?>">
    <?= $this->renderSection('pageStyles') ?>
</head>
<body class="bg-body-tertiary">
    <main class="container">
        <?= $this->renderSection('main') ?>
    </main>
    <script src="<?= base_url('assets/build/app.js') ?>" defer></script>
    <?= $this->renderSection('pageScripts') ?>
</body>
</html>
