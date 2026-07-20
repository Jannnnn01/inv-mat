<?= $this->extend('layouts/app') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?= $this->include('partials/flash') ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="h3">Dashboard</h1>
        <p class="text-body-secondary">La autenticación y los catálogos base están disponibles.</p>
        <div class="d-flex flex-wrap gap-2">
            <?php if (auth()->user()?->can('materials.view')): ?>
                <a class="btn btn-primary" href="<?= url_to('materials') ?>">Ver materiales</a>
            <?php endif ?>
            <?php if (auth()->user()?->can('users.manage')): ?>
                <a class="btn btn-outline-primary" href="<?= url_to('admin-users') ?>">Gestionar usuarios</a>
            <?php endif ?>
            <a class="btn btn-outline-secondary" href="<?= url_to('account-password') ?>">Cambiar contraseña</a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
