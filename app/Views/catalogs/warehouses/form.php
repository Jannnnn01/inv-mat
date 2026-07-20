<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $record ? 'Editar bodega' : 'Nueva bodega' ?><?= $this->endSection() ?>
<?= $this->section('main') ?>
<div class="card border-0 shadow-sm app-form-card mx-auto"><div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-4"><h1 class="h4 mb-0"><?= $record ? 'Editar bodega' : 'Nueva bodega' ?></h1><a class="btn btn-sm btn-outline-secondary" href="<?= url_to('warehouses') ?>">Volver</a></div>
    <?= $this->include('partials/flash') ?>
    <form method="post" action="<?= $record ? url_to('warehouses-update', $record['id']) : url_to('warehouses-create') ?>"><?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="code">Código</label><input class="form-control" id="code" name="code" value="<?= esc(old('code') ?: ($record['code'] ?? '')) ?>" maxlength="30" pattern="[A-Za-z0-9.-]+" required></div>
        <div class="mb-3"><label class="form-label" for="name">Nombre</label><input class="form-control" id="name" name="name" value="<?= esc(old('name') ?: ($record['name'] ?? '')) ?>" maxlength="120" required></div>
        <div class="mb-4"><label class="form-label" for="description">Descripción</label><textarea class="form-control" id="description" name="description" maxlength="1000" rows="3"><?= esc(old('description') ?: ($record['description'] ?? '')) ?></textarea></div>
        <?php if ($record && $record['is_main']): ?><div class="alert alert-info">La bodega principal puede editarse, pero no desactivarse.</div><?php endif ?>
        <button class="btn btn-primary" type="submit">Guardar</button>
    </form>
</div></div>
<?= $this->endSection() ?>
