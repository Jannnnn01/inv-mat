<?= $this->extend('layouts/app') ?>

<?= $this->section('title') ?>Auditoría<?= $this->endSection() ?>

<?= $this->section('main') ?>
<section class="app-page-section" aria-labelledby="audit-title">
    <div class="app-page-header">
        <div>
            <p class="app-eyebrow mb-1">Administración</p>
            <h1 class="h2 mb-1" id="audit-title">Auditoría</h1>
            <p class="app-page-description mb-0">Registro inmutable de accesos administrativos y operaciones relevantes.</p>
        </div>
    </div>

    <?= $this->include('partials/flash') ?>

    <form class="card" method="get" action="<?= url_to('admin-audit') ?>">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-xl-2">
                    <label class="form-label" for="date_from">Desde</label>
                    <input class="form-control" id="date_from" name="date_from" type="date" value="<?= esc($filters['date_from']) ?>">
                </div>
                <div class="col-sm-6 col-xl-2">
                    <label class="form-label" for="date_to">Hasta</label>
                    <input class="form-control" id="date_to" name="date_to" type="date" value="<?= esc($filters['date_to']) ?>">
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label" for="actor_user_id">Usuario</label>
                    <select class="form-select" id="actor_user_id" name="actor_user_id">
                        <option value="">Todos</option>
                        <?php foreach ($actors as $actor): ?>
                            <option value="<?= (int) $actor['actor_user_id'] ?>" <?= $filters['actor_user_id'] === (int) $actor['actor_user_id'] ? 'selected' : '' ?>><?= esc($actor['actor_name'] ?: 'Usuario #' . $actor['actor_user_id']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label" for="module">Módulo</label>
                    <select class="form-select" id="module" name="module">
                        <option value="">Todos</option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?= esc($module) ?>" <?= $filters['module'] === $module ? 'selected' : '' ?>><?= esc(ucfirst($module)) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label" for="result">Resultado</label>
                    <select class="form-select" id="result" name="result">
                        <option value="">Todos</option>
                        <option value="SUCCESS" <?= $filters['result'] === 'SUCCESS' ? 'selected' : '' ?>>Exitoso</option>
                        <option value="FAILURE" <?= $filters['result'] === 'FAILURE' ? 'selected' : '' ?>>Fallido</option>
                        <option value="DENIED" <?= $filters['result'] === 'DENIED' ? 'selected' : '' ?>>Denegado</option>
                    </select>
                </div>
                <div class="col-md-6 col-xl-2">
                    <label class="form-label" for="action">Acción</label>
                    <input class="form-control" id="action" name="action" maxlength="120" value="<?= esc($filters['action']) ?>" placeholder="Ej. usuarios">
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                <a class="btn btn-outline-secondary" href="<?= url_to('admin-audit') ?>">Limpiar</a>
                <button class="btn btn-primary" type="submit">Aplicar filtros</button>
            </div>
        </div>
    </form>

    <div class="card app-table-card">
        <div class="app-table-responsive">
            <table class="table app-data-table align-middle mb-0">
                <caption class="visually-hidden">Eventos registrados en auditoría</caption>
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">Módulo</th>
                        <th scope="col">Acción</th>
                        <th scope="col">Resultado</th>
                        <th scope="col">Registro</th>
                        <th scope="col" class="text-end">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td data-label="Fecha"><time datetime="<?= esc($event['occurred_at']) ?>"><?= esc($event['occurred_at']) ?></time></td>
                            <td data-label="Usuario"><?= esc($event['actor_name'] ?: 'Sistema o visitante') ?></td>
                            <td data-label="Módulo"><span class="badge text-bg-light border"><?= esc($event['module']) ?></span></td>
                            <td data-label="Acción"><span class="font-monospace app-audit-action"><?= esc($event['action']) ?></span></td>
                            <td data-label="Resultado">
                                <?php $resultClass = ['SUCCESS' => 'active', 'FAILURE' => 'inactive', 'DENIED' => 'danger'][$event['result']] ?? 'inactive'; ?>
                                <span class="app-status app-status-<?= esc($resultClass) ?>">
                                    <span class="app-status-dot" aria-hidden="true"></span>
                                    <?= esc(['SUCCESS' => 'Exitoso', 'FAILURE' => 'Fallido', 'DENIED' => 'Denegado'][$event['result']] ?? $event['result']) ?>
                                </span>
                            </td>
                            <td data-label="Registro"><?= esc($event['entity_type'] ?: '—') ?><?= $event['entity_id'] ? ' #' . esc($event['entity_id']) : '' ?></td>
                            <td data-label="Detalle" class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url_to('admin-audit-show', $event['id']) ?>">Ver detalle</a></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($events === []): ?>
                        <tr><td class="app-empty-state" colspan="7">No hay eventos que coincidan con los filtros.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>

    <div><?= $pager->links('default', 'app_full') ?></div>

    <p class="small text-body-secondary mb-0">Los eventos no pueden editarse ni eliminarse desde la aplicación y se conservan por un mínimo de dos años.</p>
</section>
<?= $this->endSection() ?>
