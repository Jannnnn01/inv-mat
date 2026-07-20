<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Unidades<?= $this->endSection() ?>
<?= $this->section('main') ?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Unidades de medida</h1><p class="text-body-secondary mb-0">Unidades disponibles para los materiales.</p></div><?php if (auth()->user()?->can('units.manage')): ?><a class="btn btn-primary" href="<?= url_to('units-new') ?>">Nueva unidad</a><?php endif ?></div>
<?= $this->include('partials/flash') ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Código</th><th>Nombre</th><th>Símbolo</th><th>Estado</th><?php if (auth()->user()?->can('units.manage')): ?><th class="text-end">Acciones</th><?php endif ?></tr></thead><tbody>
<?php foreach ($records as $record): ?><tr><td><?= esc($record['code']) ?></td><td><?= esc($record['name']) ?></td><td><?= esc($record['symbol']) ?></td><td><span class="badge text-bg-<?= $record['active'] ? 'success' : 'secondary' ?>"><?= $record['active'] ? 'Activa' : 'Inactiva' ?></span></td><?php if (auth()->user()?->can('units.manage')): ?><td><div class="d-flex justify-content-end gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= url_to('units-edit', $record['id']) ?>">Editar</a><form method="post" action="<?= url_to('units-toggle', $record['id']) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-<?= $record['active'] ? 'danger' : 'success' ?>" type="submit"><?= $record['active'] ? 'Desactivar' : 'Activar' ?></button></form></div></td><?php endif ?></tr><?php endforeach ?>
</tbody></table></div></div><div class="mt-3"><?= $pager->links() ?></div>
<?= $this->endSection() ?>
