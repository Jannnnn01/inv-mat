<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Acceso al sistema institucional de inventario">
    <title><?= esc($this->renderSection('title')) ?> | Inventario</title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('assets/brand/inventory-mark.svg?v=2') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/build/app.css') ?>">
    <?= $this->renderSection('pageStyles') ?>
</head>
<body class="app-auth-body">
    <a class="app-skip-link" href="#auth-main">Saltar al formulario de acceso</a>
    <main class="app-auth-shell" id="auth-main">
        <section class="app-auth-story" aria-label="Sistema institucional de inventario">
            <div>
                <a class="app-auth-brand" href="<?= site_url('/') ?>" aria-label="Inventario institucional, inicio">
                    <img src="<?= base_url('assets/brand/inventory-mark.svg') ?>" alt="" width="64" height="64">
                    <span>
                        <strong>Inventario</strong>
                        <small>Gestión institucional de materiales</small>
                    </span>
                </a>

                <div class="app-auth-intro">
                    <span class="app-auth-eyebrow">Control claro y seguro</span>
                    <h1>Todo el inventario,<br>en un solo lugar.</h1>
                    <p>Administra existencias, entradas, salidas y solicitudes con trazabilidad en cada movimiento.</p>
                </div>

                <ul class="app-auth-benefits" aria-label="Características principales">
                    <li>
                        <span class="app-auth-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="m4 7 8-4 8 4-8 4-8-4Z"></path><path d="M4 7v10l8 4 8-4V7M12 11v10"></path></svg>
                        </span>
                        <span><strong>Existencias actualizadas</strong><small>Consulta el stock disponible por bodega.</small></span>
                    </li>
                    <li>
                        <span class="app-auth-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M7 7h13M16 3l4 4-4 4M17 17H4M8 13l-4 4 4 4"></path></svg>
                        </span>
                        <span><strong>Movimientos trazables</strong><small>Entradas, salidas, ajustes y reversiones.</small></span>
                    </li>
                    <li>
                        <span class="app-auth-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 3 4 6v5c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6l-8-3Z"></path><path d="m9 12 2 2 4-4"></path></svg>
                        </span>
                        <span><strong>Acceso protegido</strong><small>Roles, permisos y auditoría de acciones.</small></span>
                    </li>
                </ul>
            </div>
            <p class="app-auth-caption">Sistema de control de materiales</p>
        </section>

        <section class="app-auth-content" aria-label="Acceso al sistema">
            <?= $this->renderSection('main') ?>
        </section>
    </main>
    <script src="<?= base_url('assets/build/app.js') ?>" defer></script>
    <?= $this->renderSection('pageScripts') ?>
</body>
</html>
