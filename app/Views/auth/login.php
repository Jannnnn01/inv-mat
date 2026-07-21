<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Iniciar sesión<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="app-auth-card">
    <div class="app-auth-mobile-brand" aria-hidden="true">
        <img src="<?= base_url('assets/brand/inventory-mark.svg') ?>" alt="" width="52" height="52">
        <span><strong>Inventario</strong><small>Control de materiales</small></span>
    </div>

    <header class="app-auth-card-header">
        <span class="app-auth-kicker">Bienvenido</span>
        <h2>Iniciar sesión</h2>
        <p>Ingresa tus credenciales para continuar.</p>
    </header>

    <?php if (session('error') !== null) : ?>
        <div class="alert alert-danger app-auth-alert" role="alert"><?= esc(session('error')) ?></div>
    <?php elseif (session('errors') !== null) : ?>
        <div class="alert alert-danger app-auth-alert" role="alert">
            <?php foreach ((array) session('errors') as $error) : ?>
                <div><?= esc($error) ?></div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if (session('message') !== null) : ?>
        <div class="alert alert-success app-auth-alert" role="status"><?= esc(session('message')) ?></div>
    <?php endif ?>

    <form action="<?= url_to('login') ?>" method="post" class="app-auth-form" data-loading-form>
        <?= csrf_field() ?>

        <div>
            <label class="form-label" for="loginEmail">Correo electrónico</label>
            <div class="app-auth-input-wrap">
                <span class="app-auth-input-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path></svg>
                </span>
                <input type="email" class="form-control" id="loginEmail" name="email" inputmode="email" autocomplete="email" placeholder="nombre@institucion.edu" value="<?= old('email') ?>" required autofocus>
            </div>
        </div>

        <div>
            <div class="d-flex justify-content-between align-items-center gap-3">
                <label class="form-label" for="loginPassword">Contraseña</label>
                <?php if (setting('Auth.allowMagicLinkLogins')) : ?>
                    <a class="app-auth-inline-link" href="<?= url_to('magic-link') ?>">¿Olvidaste tu contraseña?</a>
                <?php endif ?>
            </div>
            <div class="app-auth-input-wrap">
                <span class="app-auth-input-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"></path></svg>
                </span>
                <input type="password" class="form-control app-auth-password" id="loginPassword" name="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>
                <button class="app-password-toggle" type="button" data-password-toggle="loginPassword" aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary app-auth-submit">
            <span>Ingresar al sistema</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
        </button>
    </form>

    <div class="app-auth-security-note">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6v5c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6l-8-3Z"></path><path d="m9 12 2 2 4-4"></path></svg>
        <span>Acceso protegido. Tus credenciales se transmiten de forma segura.</span>
    </div>
</div>
<?= $this->endSection() ?>
