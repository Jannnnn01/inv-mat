<?= $this->extend('layouts/app') ?>

<?= $this->section('title') ?>Detalle de auditoría<?= $this->endSection() ?>

<?= $this->section('main') ?>
<section class="app-page-section" aria-labelledby="audit-detail-title">
    <div class="app-page-header">
        <div>
            <p class="app-eyebrow mb-1">Auditoría · Evento #<?= (int) $event['id'] ?></p>
            <h1 class="h2 mb-1" id="audit-detail-title">Detalle del evento</h1>
            <p class="app-page-description mb-0">Información protegida y solo disponible para administradores autorizados.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url_to('admin-audit') ?>">Volver a auditoría</a>
    </div>

    <div class="card">
        <div class="card-body p-4">
            <dl class="row app-audit-details mb-0">
                <dt class="col-sm-4 col-lg-3">Fecha y hora</dt><dd class="col-sm-8 col-lg-9"><?= esc($event['occurred_at']) ?></dd>
                <dt class="col-sm-4 col-lg-3">Usuario</dt><dd class="col-sm-8 col-lg-9"><?= esc($event['actor_name'] ?: 'Sistema o visitante') ?><?= $event['actor_user_id'] ? ' (#' . (int) $event['actor_user_id'] . ')' : '' ?></dd>
                <dt class="col-sm-4 col-lg-3">Acción</dt><dd class="col-sm-8 col-lg-9"><code><?= esc($event['action']) ?></code></dd>
                <dt class="col-sm-4 col-lg-3">Módulo</dt><dd class="col-sm-8 col-lg-9"><?= esc($event['module']) ?></dd>
                <dt class="col-sm-4 col-lg-3">Ruta</dt><dd class="col-sm-8 col-lg-9"><code><?= esc($event['http_method'] . ' ' . $event['request_path']) ?></code></dd>
                <dt class="col-sm-4 col-lg-3">Resultado</dt><dd class="col-sm-8 col-lg-9"><?= esc($event['result']) ?></dd>
                <dt class="col-sm-4 col-lg-3">Registro afectado</dt><dd class="col-sm-8 col-lg-9"><?= esc($event['entity_type'] ?: 'No aplica') ?><?= $event['entity_id'] ? ' #' . esc($event['entity_id']) : '' ?></dd>
                <dt class="col-sm-4 col-lg-3">Dirección IP</dt><dd class="col-sm-8 col-lg-9"><?= esc($event['ip_address'] ?: 'No disponible') ?><?= ! $canViewSensitive ? ' (parcialmente oculta)' : '' ?></dd>
                <dt class="col-sm-4 col-lg-3">Agente de usuario</dt><dd class="col-sm-8 col-lg-9"><?= esc($event['user_agent'] ?: 'No disponible') ?></dd>
                <dt class="col-sm-4 col-lg-3">Conservar hasta</dt><dd class="col-sm-8 col-lg-9"><?= esc($event['retention_until']) ?></dd>
            </dl>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach (['old_values_decoded' => 'Valores anteriores', 'new_values_decoded' => 'Valores registrados'] as $key => $label): ?>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3"><?= esc($label) ?></h2>
                        <?php if ($event[$key] === null): ?>
                            <p class="text-body-secondary mb-0">No se registraron valores para este evento.</p>
                        <?php else: ?>
                            <pre class="app-audit-json mb-0"><?= esc((string) json_encode($event[$key], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</section>
<?= $this->endSection() ?>
