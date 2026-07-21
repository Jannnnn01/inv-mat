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

$navIcons = [
    'dashboard'  => '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect>',
    'stocks'     => '<path d="m4 7.5 8-4.5 8 4.5-8 4.5-8-4.5Z"></path><path d="m4 12 8 4.5 8-4.5M4 16.5 12 21l8-4.5"></path>',
    'alerts'     => '<path d="M10.3 3.7 2.6 18a2 2 0 0 0 1.8 3h15.2a2 2 0 0 0 1.8-3L13.7 3.7a2 2 0 0 0-3.4 0Z"></path><path d="M12 9v4M12 17h.01"></path>',
    'valuation'  => '<circle cx="12" cy="12" r="9"></circle><path d="M15.5 8.5h-5a2 2 0 0 0 0 4h3a2 2 0 0 1 0 4h-5M12 6.5v11"></path>',
    'movements'  => '<path d="M7 7h13M16 3l4 4-4 4M17 17H4M8 13l-4 4 4 4"></path>',
    'dispatch'   => '<path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="18" cy="18" r="2"></circle>',
    'requests'   => '<rect x="5" y="4" width="14" height="17" rx="2"></rect><path d="M9 4.5V3h6v1.5M9 10h6M9 14h6M9 18h4"></path>',
    'materials'  => '<path d="m4 7 8-4 8 4-8 4-8-4Z"></path><path d="M4 7v10l8 4 8-4V7M12 11v10"></path>',
    'categories' => '<path d="M20 13 13 20l-9-9V4h7l9 9Z"></path><circle cx="8.5" cy="8.5" r="1"></circle>',
    'suppliers'  => '<path d="M3 21V8l6-4v17M9 10l6-3v14M15 12l6-2v11M6 11h.01M6 15h.01M12 12h.01M12 16h.01M18 14h.01M18 18h.01"></path>',
    'recipients' => '<path d="M4 21V8l8-5 8 5v13M8 21v-6h8v6"></path><path d="M8 10h.01M12 10h.01M16 10h.01"></path>',
    'units'      => '<path d="m4 17 13-13 3 3L7 20l-3-3Z"></path><path d="m13 8 3 3M10 11l2 2M7 14l3 3"></path>',
    'warehouses' => '<path d="m3 10 9-6 9 6v11H3V10Z"></path><path d="M7 21v-7h10v7M7 10h.01M12 10h.01M17 10h.01"></path>',
    'users'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path>',
    'audit'      => '<path d="M12 3 4 6v5c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6l-8-3Z"></path><path d="m9 12 2 2 4-4"></path>',
    'reports'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"></path>',
];

$navSections = [
    [
        'id'    => 'navInventory',
        'label' => 'Inventario',
        'items' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => 'dashboard.view', 'matches' => ['dashboard'], 'icon' => 'dashboard'],
            ['label' => 'Existencias', 'route' => 'inventory-stocks', 'permission' => 'stock.view', 'matches' => ['inventario/existencias'], 'icon' => 'stocks'],
            ['label' => 'Alertas', 'route' => 'inventory-alerts', 'permission' => 'stock.view', 'matches' => ['inventario/alertas'], 'icon' => 'alerts'],
            ['label' => 'Valoraciones', 'route' => 'inventory-valuations', 'permission' => 'financial.view', 'matches' => ['inventario/valoraciones'], 'icon' => 'valuation'],
            ['label' => 'Movimientos', 'route' => 'inventory-movements', 'permission' => 'inventory.movements.view', 'matches' => ['inventario/movimientos'], 'icon' => 'movements'],
            ['label' => 'Pendientes de despacho', 'route' => 'inventory-dispatch-pending', 'permission' => 'inventory.movements.view', 'matches' => ['inventario/despachos'], 'icon' => 'dispatch'],
            ['label' => 'Requisiciones', 'route' => 'dispatch-requests', 'permission' => 'inventory.dispatch_requests.create', 'matches' => ['inventario/requisiciones'], 'icon' => 'requests'],
            ['label' => 'Solicitudes', 'route' => 'inventory-requests', 'permission' => 'inventory.adjustments.request', 'matches' => ['inventario/solicitudes'], 'icon' => 'requests'],
            ['label' => 'Reportes', 'route' => 'reports', 'permission' => 'reports.view', 'matches' => ['reportes'], 'icon' => 'reports'],
        ],
    ],
    [
        'id'    => 'navCatalogs',
        'label' => 'Catálogos',
        'items' => [
            ['label' => 'Materiales', 'route' => 'materials', 'permission' => 'materials.view', 'matches' => ['catalogos/materiales'], 'icon' => 'materials'],
            ['label' => 'Categorías', 'route' => 'categories', 'permission' => 'categories.view', 'matches' => ['catalogos/categorias'], 'icon' => 'categories'],
            ['label' => 'Proveedores', 'route' => 'suppliers', 'permission' => 'suppliers.view', 'matches' => ['catalogos/proveedores'], 'icon' => 'suppliers'],
            ['label' => 'Destinatarios', 'route' => 'recipients', 'permission' => 'recipients.view', 'matches' => ['catalogos/destinatarios'], 'icon' => 'recipients'],
            ['label' => 'Unidades', 'route' => 'units', 'permission' => 'units.view', 'matches' => ['catalogos/unidades'], 'icon' => 'units'],
            ['label' => 'Bodegas', 'route' => 'warehouses', 'permission' => 'warehouses.manage', 'matches' => ['catalogos/bodegas'], 'icon' => 'warehouses'],
        ],
    ],
    [
        'id'    => 'navAdmin',
        'label' => 'Administración',
        'items' => [
            ['label' => 'Usuarios', 'route' => 'admin-users', 'permission' => 'users.manage', 'matches' => ['admin/usuarios'], 'icon' => 'users'],
            ['label' => 'Auditoría', 'route' => 'admin-audit', 'permission' => 'audit.view', 'matches' => ['admin/auditoria'], 'icon' => 'audit'],
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
    <link rel="icon" type="image/svg+xml" href="<?= base_url('assets/brand/inventory-mark.svg?v=2') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/build/app.css') ?>">
</head>
<body class="app-layout bg-body-tertiary">
<a class="app-skip-link" href="#main-content">Saltar al contenido principal</a>
<div class="app-shell">
    <aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
        <div class="app-sidebar-header">
            <a class="app-brand" href="<?= url_to('dashboard') ?>" id="appSidebarLabel" aria-label="Ir al dashboard">
                <span class="app-brand-mark" aria-hidden="true">
                    <img src="<?= base_url('assets/brand/inventory-mark.svg') ?>" alt="" width="40" height="40">
                </span>
                <span class="app-brand-text">
                    <span>Inventario</span>
                    <small>Control de materiales</small>
                </span>
            </a>
            <button class="btn btn-sm btn-outline-light d-lg-none app-icon-button" type="button" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Cerrar menú">
                <svg class="app-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M6 6l12 12M18 6 6 18"></path>
                </svg>
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
                        <svg class="app-nav-chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                            <path d="m6 8 4 4 4-4"></path>
                        </svg>
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
                                    <span class="app-nav-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" focusable="false"><?= $navIcons[$item['icon']] ?></svg>
                                    </span>
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
                    <svg class="app-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M4 7h16M4 12h16M4 17h16"></path>
                    </svg>
                </button>
                <button class="btn btn-outline-secondary d-none d-lg-inline-flex app-icon-button" type="button" data-sidebar-toggle aria-label="Contraer menú" title="Contraer menú">
                    <svg class="app-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M4 7h16M4 12h16M4 17h16"></path>
                    </svg>
                </button>
                <div>
                    <p class="app-kicker mb-0">Sistema institucional</p>
                    <p class="app-page-title mb-0"><?= esc($pageTitle) ?></p>
                </div>
            </div>
            <form method="post" action="<?= url_to('logout') ?>" class="m-0" data-loading-form>
                <?= csrf_field() ?>
                <button class="btn btn-outline-primary app-logout-button" type="submit">
                    <svg class="app-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M9 12h9"></path>
                    </svg>
                    <span>Cerrar sesión</span>
                </button>
            </form>
        </header>

        <main class="app-main" id="main-content" tabindex="-1">
            <?= $this->renderSection('main') ?>
        </main>
    </div>
</div>

<script src="<?= base_url('assets/build/app.js') ?>" defer></script>
</body>
</html>
