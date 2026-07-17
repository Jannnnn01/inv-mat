<?= $this->extend('auth/layout') ?>

<?= $this->section('title') ?>Cambiar contrasena<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-6 shadow-sm">
        <div class="card-body">
            <h1 class="h4 mb-4">Cambiar contrasena</h1>

            <?php if (session('error') !== null): ?>
                <div class="alert alert-danger"><?= esc(session('error')) ?></div>
            <?php endif ?>
            <?php if (session('errors') !== null): ?>
                <div class="alert alert-danger">
                    <?php foreach ((array) session('errors') as $error): ?>
                        <div><?= esc($error) ?></div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <form method="post" action="<?= url_to('account-password') ?>">
                <?= csrf_field() ?>

                <?php if (! $recoveryAuthorized): ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Contrasena actual</label>
                        <input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    </div>
                <?php endif ?>

                <div class="mb-3">
                    <label class="form-label" for="password">Nueva contrasena</label>
                    <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password_confirm">Confirmar contrasena</label>
                    <input class="form-control" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
                </div>

                <button class="btn btn-primary" type="submit">Guardar contrasena</button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
