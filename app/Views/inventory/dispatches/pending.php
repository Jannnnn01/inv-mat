<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Pendientes de despacho<?= $this->endSection() ?>
<?= $this->section('main') ?>
<div class="d-flex justify-content-between align-items-center gap-3 mb-4"><div><h1 class="h3 mb-1">Pendientes de despacho</h1><p class="text-body-secondary mb-0">Cantidades aún no entregadas de guías originales.</p></div><a class="btn btn-outline-secondary" href="<?= url_to('inventory-movements') ?>">Movimientos</a></div>
<?= $this->include('partials/flash') ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Guía original</th><th>Movimiento</th><th>Bodega</th><th>Fecha</th><th class="text-end">Líneas pendientes</th><th class="text-end">Cantidad pendiente</th><th></th></tr></thead><tbody>
<?php foreach ($records as $record): ?><tr><td><?= esc($record['guide_number'] ?: 'Sin número') ?></td><td><a class="font-monospace" href="<?= url_to('inventory-movement-show', $record['movement_id']) ?>"><?= esc($record['movement_number']) ?></a></td><td><?= esc($record['warehouse_name']) ?></td><td><?= esc($record['issue_date'] ?? '—') ?></td><td class="text-end"><?= esc((string) $record['pending_lines']) ?></td><td class="text-end fw-semibold text-warning"><?= esc(number_format((float) $record['pending_quantity'], 3, ',', '.')) ?></td><td class="text-end"><?php if (auth()->user()?->can('inventory.exits.create')): ?><a class="btn btn-sm btn-primary" href="<?= url_to('inventory-dispatch-delivery-new', $record['id']) ?>">Registrar entrega</a><?php else: ?><span class="text-body-secondary">Consulta</span><?php endif ?></td></tr><?php endforeach ?>
<?php if ($records === []): ?><tr><td colspan="7" class="text-center text-body-secondary py-5">No existen cantidades pendientes de despacho.</td></tr><?php endif ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
