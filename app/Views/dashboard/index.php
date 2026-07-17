<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Inventario</title>
    <link rel="stylesheet" href="<?= base_url('assets/build/app.css') ?>">
</head>
<body class="bg-body-tertiary">
    <main class="container py-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3">Dashboard</h1>
                <p class="text-body-secondary">Sesion autenticada correctamente.</p>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-primary" href="<?= url_to('account-password') ?>">Cambiar contrasena</a>
                    <form method="post" action="<?= url_to('logout') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-danger" type="submit">Cerrar sesion</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <script src="<?= base_url('assets/build/app.js') ?>" defer></script>
</body>
</html>
