<?= $this->extend('auth/layout') ?>

<?= $this->section('title') ?>Cambiar contraseña<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="app-auth-card">
    <div class="app-auth-mobile-brand" aria-hidden="true">
        <img src="<?= base_url('assets/brand/inventory-mark.svg') ?>" alt="" width="52" height="52">
        <span><strong>Inventario</strong><small>Control de materiales</small></span>
    </div>

    <header class="app-auth-card-header">
        <span class="app-auth-kicker">Seguridad de la cuenta</span>
        <h2>Cambiar contraseña</h2>
        <p>Utiliza al menos 12 caracteres y evita reutilizar contraseñas de otros servicios.</p>
    </header>

    <?php if (session('error') !== null): ?>
        <div class="alert alert-danger app-auth-alert" role="alert"><?= esc(session('error')) ?></div>
    <?php endif ?>
    <?php if (session('errors') !== null): ?>
        <div class="alert alert-danger app-auth-alert" role="alert">
            <?php foreach ((array) session('errors') as $error): ?><div><?= esc($error) ?></div><?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if (! $canCancel): ?>
        <div class="alert alert-info app-auth-alert" role="status">
            Debes establecer una nueva contraseña antes de continuar al sistema.
        </div>
    <?php endif ?>

    <form method="post" action="<?= url_to('account-password') ?>" class="app-auth-form" data-loading-form>
        <?= csrf_field() ?>

        <?php if (! $recoveryAuthorized): ?>
            <div>
                <label class="form-label" for="current_password">Contraseña actual</label>
                <div class="app-auth-input-wrap">
                    <span class="app-auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg></span>
                    <input class="form-control app-auth-password" id="current_password" name="current_password" type="password" autocomplete="current-password" placeholder="Ingresa tu contraseña actual" required>
                    <button class="app-password-toggle" type="button" data-password-toggle="current_password" aria-label="Mostrar contraseña actual" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg></button>
                </div>
            </div>
        <?php endif ?>

        <div>
            <label class="form-label" for="password">Nueva contraseña</label>
            <div class="app-auth-input-wrap">
                <span class="app-auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 4 6v5c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6l-8-3Z"></path></svg></span>
                <input class="form-control app-auth-password" id="password" name="password" type="password" autocomplete="new-password" placeholder="Mínimo 12 caracteres" minlength="12" maxlength="72" required>
                <button class="app-password-toggle" type="button" data-password-toggle="password" aria-label="Mostrar nueva contraseña" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg></button>
            </div>
        </div>

        <div>
            <label class="form-label" for="password_confirm">Confirmar nueva contraseña</label>
            <div class="app-auth-input-wrap">
                <span class="app-auth-input-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"></path></svg></span>
                <input class="form-control app-auth-password" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" placeholder="Repite la nueva contraseña" minlength="12" maxlength="72" required>
                <button class="app-password-toggle" type="button" data-password-toggle="password_confirm" aria-label="Mostrar confirmación de contraseña" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg></button>
            </div>
        </div>

        <div class="app-auth-actions">
            <?php if ($canCancel): ?>
                <a class="btn btn-outline-secondary" href="<?= url_to('dashboard') ?>">Volver al dashboard</a>
            <?php endif ?>
            <button class="btn btn-primary app-auth-submit m-0" type="submit">
                <span>Guardar contraseña</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
            </button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
