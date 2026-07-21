<?= $this->extend('layouts/app') ?>

<?= $this->section('title') ?>Crear usuario<?= $this->endSection() ?>

<?= $this->section('main') ?>
<section class="app-page-section" aria-labelledby="new-user-title">
    <div class="card mx-auto app-form-card">
        <div class="card-body p-4 p-md-5">
            <div class="app-form-heading">
                <div>
                    <p class="app-eyebrow mb-1">Administración</p>
                    <h1 class="h3 mb-1" id="new-user-title">Crear usuario</h1>
                    <p class="app-page-description mb-0">Registra la cuenta y envía las instrucciones de acceso.</p>
                </div>
                <a class="btn btn-outline-secondary" href="<?= url_to('admin-users') ?>">Volver</a>
            </div>

            <?= $this->include('partials/flash') ?>

            <form method="post" action="<?= url_to('admin-users-create') ?>" data-loading-form>
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
                <div class="app-form-actions">
                    <a class="btn btn-outline-secondary" href="<?= url_to('admin-users') ?>">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Crear y enviar invitación</button>
                </div>
            </form>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
