<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Bodegas<?= $this->endSection() ?>
<?= $this->section('main') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h1 class="h3 mb-1">Bodegas</h1><p class="text-body-secondary mb-0">La existencia siempre estará separada por bodega.</p></div>
    <a class="btn btn-primary" href="<?= url_to('warehouses-new') ?>">Nueva bodega</a>
</div>
<?= $this->include('partials/flash') ?>
<form class="row g-2 mb-3" method="get"><div class="col-sm-6 col-lg-4"><input class="form-control" name="q" value="<?= esc($search) ?>" placeholder="Buscar por código o nombre"></div><div class="col-auto"><button class="btn btn-outline-secondary">Buscar</button></div></form>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($records as $record): ?>
        <tr><td><?= esc($record['code']) ?></td><td><?= esc($record['name']) ?></td><td><?= $record['is_main'] ? 'Principal' : 'Secundaria' ?></td><td><span class="badge text-bg-<?= $record['active'] ? 'success' : 'secondary' ?>"><?= $record['active'] ? 'Activa' : 'Inactiva' ?></span></td>
        <td><div class="d-flex justify-content-end gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= url_to('warehouses-edit', $record['id']) ?>">Editar</a><form method="post" action="<?= url_to('warehouses-toggle', $record['id']) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-<?= $record['active'] ? 'danger' : 'success' ?>" type="submit" <?= $record['is_main'] ? 'disabled' : '' ?>><?= $record['active'] ? 'Desactivar' : 'Activar' ?></button></form></div></td></tr>
    <?php endforeach ?>
    </tbody>
</table></div></div><div class="mt-3"><?= $pager->links() ?></div>
<?= $this->endSection() ?>
