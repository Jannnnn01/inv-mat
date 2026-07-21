<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Movimientos<?= $this->endSection() ?>
<?= $this->section('main') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h1 class="h3 mb-1">Movimientos</h1><p class="text-body-secondary mb-0">Historial inmutable de entradas, salidas, ajustes y reversiones.</p></div>
    <div class="d-flex gap-2">
        <?php if (auth()->user()?->can('inventory.entries.create')): ?><a class="btn btn-success" href="<?= url_to('inventory-entry-new') ?>">Nueva entrada</a><?php endif ?>
        <?php if (auth()->user()?->can('inventory.exits.create')): ?><a class="btn btn-primary" href="<?= url_to('inventory-exit-new') ?>">Nueva salida</a><?php endif ?>
    </div>
</div>
<?= $this->include('partials/flash') ?>
<form class="card border-0 shadow-sm mb-3" method="get"><div class="card-body row g-3 align-items-end">
    <div class="col-md-3"><label class="form-label" for="type">Tipo</label><select class="form-select" id="type" name="type"><option value="">Todos</option><?php foreach (['ENTRY' => 'Entrada', 'EXIT' => 'Salida', 'ADJUSTMENT' => 'Ajuste', 'REVERSAL' => 'Reversión'] as $value => $label): ?><option value="<?= $value ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?></select></div>
    <div class="col-md-3"><label class="form-label" for="warehouse_id">Bodega</label><select class="form-select" id="warehouse_id" name="warehouse_id"><option value="">Todas</option><?php foreach ($warehouses as $warehouse): ?><option value="<?= esc((string) $warehouse['id']) ?>" <?= $filters['warehouseId'] === (int) $warehouse['id'] ? 'selected' : '' ?>><?= esc($warehouse['name']) ?></option><?php endforeach ?></select></div>
    <div class="col-md-2"><label class="form-label" for="from">Desde</label><input class="form-control" type="date" id="from" name="from" value="<?= esc($filters['from']) ?>"></div>
    <div class="col-md-2"><label class="form-label" for="to">Hasta</label><input class="form-control" type="date" id="to" name="to" value="<?= esc($filters['to']) ?>"></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filtrar</button></div>
</div></form>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Número</th><th>Fecha</th><th>Tipo</th><th>Bodega</th><th>Registrado por</th><th>Valoración</th><th></th></tr></thead><tbody>
<?php $typeLabels = ['ENTRY' => 'Entrada', 'EXIT' => 'Salida', 'ADJUSTMENT' => 'Ajuste', 'REVERSAL' => 'Reversión']; ?>
<?php foreach ($records as $record): ?><tr><td class="font-monospace"><?= esc($record['movement_number']) ?></td><td><?= esc($record['created_at']) ?></td><td><?= esc($typeLabels[$record['type']] ?? $record['type']) ?><?php if ((int) $record['is_reversed'] === 1): ?> <span class="badge text-bg-warning">Revertido</span><?php endif ?></td><td><?= esc($record['warehouse_name']) ?></td><td><?= esc($record['created_by_name']) ?></td><td><?= (int) $record['current_pending_valuation'] === 1 ? '<span class="badge text-bg-warning">Pendiente</span>' : '<span class="badge text-bg-success">Completa</span>' ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url_to('inventory-movement-show', $record['id']) ?>">Ver</a></td></tr><?php endforeach ?>
<?php if ($records === []): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">No hay movimientos para los filtros seleccionados.</td></tr><?php endif ?>
</tbody></table></div></div><div class="mt-3"><?= $pager->links() ?></div>
<?= $this->endSection() ?>
