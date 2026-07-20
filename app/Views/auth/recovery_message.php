<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Recuperacion de acceso<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-6 shadow-sm">
        <div class="card-body">
            <h1 class="h4">Revisa tu correo</h1>
            <p class="mb-0">
                Si existe una cuenta activa asociada, recibiras un enlace temporal para recuperar el acceso.
            </p>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
