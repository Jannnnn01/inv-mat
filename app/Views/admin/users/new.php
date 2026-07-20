<?= $this->extend('auth/layout') ?>

<?= $this->section('title') ?>Crear usuario<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container py-5">
    <div class="card border-0 shadow-sm mx-auto app-form-card">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h4 mb-0">Crear usuario</h1>
                <a class="btn btn-sm btn-outline-secondary" href="<?= url_to('admin-users') ?>">Volver</a>
            </div>

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

            <form method="post" action="<?= url_to('admin-users-create') ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username">Nombre de usuario</label>
                    <input class="form-control" id="username" name="username" value="<?= esc(old('username')) ?>" minlength="3" maxlength="30" pattern="[A-Za-z0-9.]+" required>
                    <div class="form-text">Solo letras, números y puntos.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input class="form-control" id="email" name="email" type="email" value="<?= esc(old('email')) ?>" maxlength="254" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="role">Rol principal</label>
                    <select class="form-select" id="role" name="role" required>
                        <?php foreach ($roles as $key => $role): ?>
                            <option value="<?= esc($key) ?>" <?= old('role') === $key ? 'selected' : '' ?>><?= esc($role['title']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Crear y enviar invitación</button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
