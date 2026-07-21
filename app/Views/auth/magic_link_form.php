<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Recuperar acceso<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="app-auth-card">
    <div class="app-auth-mobile-brand" aria-hidden="true">
        <img src="<?= base_url('assets/brand/inventory-mark.svg') ?>" alt="" width="52" height="52">
        <span><strong>Inventario</strong><small>Control de materiales</small></span>
    </div>

    <header class="app-auth-card-header">
        <span class="app-auth-kicker">Recuperación segura</span>
        <h2>Recuperar acceso</h2>
        <p>Te enviaremos un enlace temporal al correo asociado con tu cuenta.</p>
    </header>

    <?php if (session('error') !== null) : ?>
        <div class="alert alert-danger app-auth-alert" role="alert"><?= esc(session('error')) ?></div>
    <?php elseif (session('errors') !== null) : ?>
        <div class="alert alert-danger app-auth-alert" role="alert">
            <?php foreach ((array) session('errors') as $error) : ?><div><?= esc($error) ?></div><?php endforeach ?>
        </div>
    <?php endif ?>

    <form action="<?= url_to('magic-link') ?>" method="post" class="app-auth-form" data-loading-form>
        <?= csrf_field() ?>
        <div>
            <label class="form-label" for="recoveryEmail">Correo electrónico</label>
            <div class="app-auth-input-wrap">
                <span class="app-auth-input-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path></svg>
                </span>
                <input type="email" class="form-control" id="recoveryEmail" name="email" autocomplete="email" placeholder="nombre@institucion.edu" value="<?= old('email', auth()->user()->email ?? null) ?>" required autofocus>
            </div>
        </div>
        <button type="submit" class="btn btn-primary app-auth-submit">
            <span>Enviar enlace de acceso</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>
        </button>
    </form>

    <a class="app-auth-back" href="<?= url_to('login') ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
        Volver al inicio de sesión
    </a>
</div>
<?= $this->endSection() ?>
