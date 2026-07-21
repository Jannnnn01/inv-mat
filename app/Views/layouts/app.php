<?php
$user        = auth()->user();
$currentPath = trim(service('uri')->getPath(), '/');
$pageTitle   = trim($this->renderSection('title'));

$isActive = static function (array $matches) use ($currentPath): bool {
    foreach ($matches as $match) {
        if ($currentPath === $match || str_starts_with($currentPath, $match . '/')) {
            return true;
        }
    }

    return false;
};

$canSee = static fn (?string $permission): bool => $permission === null || auth()->user()?->can($permission);

$navSections = [
    [
        'id'    => 'navInventory',
        'label' => 'Inventario',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => 'dashboard.view', 'matches' => ['dashboard'], 'icon' => 'D'],
            ['label' => 'Existencias', 'route' => 'inventory-stocks', 'permission' => 'stock.view', 'matches' => ['inventario/existencias'], 'icon' => 'E'],
            ['label' => 'Alertas', 'route' => 'inventory-alerts', 'permission' => 'stock.view', 'matches' => ['inventario/alertas'], 'icon' => 'A'],
            ['label' => 'Valoraciones', 'route' => 'inventory-valuations', 'permission' => 'financial.view', 'matches' => ['inventario/valoraciones'], 'icon' => 'V'],
            ['label' => 'Movimientos', 'route' => 'inventory-movements', 'permission' => 'inventory.movements.view', 'matches' => ['inventario/movimientos'], 'icon' => 'M'],
            ['label' => 'Solicitudes', 'route' => 'inventory-requests', 'permission' => 'inventory.adjustments.request', 'matches' => ['inventario/solicitudes'], 'icon' => 'S'],
        ],
    ],
    [
        'id'    => 'navCatalogs',
        'label' => 'Catálogos',
        'items' => [
            ['label' => 'Materiales', 'route' => 'materials', 'permission' => 'materials.view', 'matches' => ['catalogos/materiales'], 'icon' => 'M'],
            ['label' => 'Categorías', 'route' => 'categories', 'permission' => 'categories.view', 'matches' => ['catalogos/categorias'], 'icon' => 'C'],
            ['label' => 'Proveedores', 'route' => 'suppliers', 'permission' => 'suppliers.view', 'matches' => ['catalogos/proveedores'], 'icon' => 'P'],
            ['label' => 'Unidades', 'route' => 'units', 'permission' => 'units.view', 'matches' => ['catalogos/unidades'], 'icon' => 'U'],
            ['label' => 'Bodegas', 'route' => 'warehouses', 'permission' => 'warehouses.manage', 'matches' => ['catalogos/bodegas'], 'icon' => 'B'],
        ],
    ],
    [
        'id'    => 'navAdmin',
        'label' => 'Administración',
        'items' => [
            ['label' => 'Usuarios', 'route' => 'admin-users', 'permission' => 'users.manage', 'matches' => ['admin/usuarios'], 'icon' => 'U'],
        ],
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sistema institucional de inventario">
    <title><?= esc($pageTitle) ?> | Inventario</title>
    <link rel="stylesheet" href="<?= base_url('assets/build/app.css') ?>">
</head>
<body class="app-layout bg-body-tertiary">
<div class="app-shell">
    <aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
        <div class="app-sidebar-header">
            <a class="app-brand" href="<?= url_to('dashboard') ?>" id="appSidebarLabel" aria-label="Ir al dashboard">
                <span class="app-brand-mark">I</span>
                <span class="app-brand-text">
                    <span>Inventario</span>
                    <small>Control de materiales</small>
                </span>
            </a>
            <button class="btn btn-sm btn-outline-light d-lg-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Cerrar menú">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="app-sidebar-body">
            <?php foreach ($navSections as $section): ?>
                <?php
                $visibleItems = array_values(array_filter(
                    $section['items'],
                    static fn (array $item): bool => $canSee($item['permission'])
                ));

                if ($visibleItems === []) {
                    continue;
                }

                $sectionIsActive = array_reduce(
                    $visibleItems,
                    static fn (bool $active, array $item): bool => $active || $isActive($item['matches']),
                    false
                );
                ?>
                <section class="app-nav-section">
                    <button
                        class="app-nav-heading <?= $sectionIsActive ? '' : 'collapsed' ?>"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?= esc($section['id']) ?>"
                        aria-expanded="<?= $sectionIsActive ? 'true' : 'false' ?>"
                        aria-controls="<?= esc($section['id']) ?>"
                    >
                        <span><?= esc($section['label']) ?></span>
                        <span class="app-nav-chevron" aria-hidden="true">v</span>
                    </button>
                    <div class="collapse <?= $sectionIsActive ? 'show' : '' ?>" id="<?= esc($section['id']) ?>">
                        <nav class="app-nav" aria-label="<?= esc($section['label']) ?>">
                            <?php foreach ($visibleItems as $item): ?>
                                <?php $itemIsActive = $isActive($item['matches']); ?>
                                <a
                                    class="app-nav-link <?= $itemIsActive ? 'active' : '' ?>"
                                    href="<?= url_to($item['route']) ?>"
                                    title="<?= esc($item['label']) ?>"
                                    <?= $itemIsActive ? 'aria-current="page"' : '' ?>
                                >
                                    <span class="app-nav-icon" aria-hidden="true"><?= esc($item['icon']) ?></span>
                                    <span class="app-nav-label"><?= esc($item['label']) ?></span>
                                </a>
                            <?php endforeach ?>
                        </nav>
                    </div>
                </section>
            <?php endforeach ?>
        </div>

        <div class="app-sidebar-footer">
            <a class="app-account-link" href="<?= url_to('account-password') ?>">
                <span class="app-account-avatar" aria-hidden="true"><?= esc(strtoupper(substr($user?->email ?? 'U', 0, 1))) ?></span>
                <span class="app-account-copy">
                    <span class="app-account-label">Mi cuenta</span>
                    <small><?= esc($user?->email ?? '') ?></small>
                </span>
            </a>
        </div>
    </aside>

    <div class="app-content">
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-primary d-lg-none app-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Abrir menú">
                    <span aria-hidden="true">&#9776;</span>
                </button>
                <button class="btn btn-outline-secondary d-none d-lg-inline-flex app-icon-button" type="button" data-sidebar-toggle aria-label="Contraer menú" title="Contraer menú">
                    <span aria-hidden="true">&#9776;</span>
                </button>
                <div>
                    <p class="app-kicker mb-0">Sistema institucional</p>
                    <p class="app-page-title mb-0"><?= esc($pageTitle) ?></p>
                </div>
            </div>
            <form method="post" action="<?= url_to('logout') ?>" class="m-0">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit">Cerrar sesión</button>
            </form>
        </header>

        <main class="app-main">
            <?= $this->renderSection('main') ?>
        </main>
    </div>
</div>

<script src="<?= base_url('assets/build/app.js') ?>" defer></script>
</body>
</html>
