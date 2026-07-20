<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sistema institucional de inventario">
    <title><?= esc($this->renderSection('title')) ?> | Inventario</title>
    <link rel="stylesheet" href="<?= base_url('assets/build/app.css') ?>">
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg bg-primary navbar-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= url_to('dashboard') ?>">Inventario</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Abrir navegación">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= url_to('dashboard') ?>">Dashboard</a></li>
                <?php if (auth()->user()?->can('materials.view')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url_to('materials') ?>">Materiales</a></li>
                <?php endif ?>
                <?php if (auth()->user()?->can('stock.view')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url_to('inventory-stocks') ?>">Existencias</a></li>
                <?php endif ?>
                <?php if (auth()->user()?->can('inventory.movements.view')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url_to('inventory-movements') ?>">Movimientos</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= url_to('inventory-requests') ?>">Solicitudes</a></li>
                <?php endif ?>
                <?php if (auth()->user()?->can('categories.view')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url_to('categories') ?>">Categorías</a></li>
                <?php endif ?>
                <?php if (auth()->user()?->can('suppliers.view')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url_to('suppliers') ?>">Proveedores</a></li>
                <?php endif ?>
                <?php if (auth()->user()?->can('units.view')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url_to('units') ?>">Unidades</a></li>
                <?php endif ?>
                <?php if (auth()->user()?->can('warehouses.manage')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url_to('warehouses') ?>">Bodegas</a></li>
                <?php endif ?>
                <?php if (auth()->user()?->can('users.manage')): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url_to('admin-users') ?>">Usuarios</a></li>
                <?php endif ?>
            </ul>
            <div class="d-flex align-items-center gap-3 text-white">
                <span class="small"><?= esc(auth()->user()?->email ?? '') ?></span>
                <form method="post" action="<?= url_to('logout') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-light" type="submit">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4">
    <?= $this->renderSection('main') ?>
</main>

<script src="<?= base_url('assets/build/app.js') ?>" defer></script>
</body>
</html>
