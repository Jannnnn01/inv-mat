<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Recuperacion de acceso<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="app-auth-card app-auth-message-card">
    <div class="app-auth-message-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path><path d="m15 17 2 2 4-4"></path></svg>
    </div>
    <header class="app-auth-card-header">
        <span class="app-auth-kicker">Solicitud recibida</span>
        <h2>Revisa tu correo</h2>
        <p>Si existe una cuenta activa asociada, recibirás un enlace temporal para recuperar el acceso.</p>
    </header>
    <a class="app-auth-back justify-content-center" href="<?= url_to('login') ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
        Volver al inicio de sesión
    </a>
</div>
<?= $this->endSection() ?>
