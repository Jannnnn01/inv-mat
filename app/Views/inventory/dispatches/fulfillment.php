<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?>Entrega pendiente<?= $this->endSection() ?>
<?= $this->section('main') ?>
<div class="card border-0 shadow-sm mx-auto app-wide-form-card"><div class="card-body p-4">
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h4 mb-1">Registrar entrega posterior</h1><p class="text-body-secondary mb-0">Guía original: <?= esc($dispatch['guide_number'] ?: $dispatch['movement_number']) ?> · <?= esc($dispatch['warehouse_name']) ?></p></div><a class="btn btn-sm btn-outline-secondary" href="<?= url_to('inventory-dispatch-pending') ?>">Volver</a></div>
<?= $this->include('partials/flash') ?>
<div class="alert alert-info">La nueva salida quedará vinculada a la guía original. El backend bloqueará cualquier cantidad superior al pendiente vigente.</div>
<form method="post" action="<?= url_to('inventory-dispatch-delivery-create', $dispatch['id']) ?>"><?= csrf_field() ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Número de la nueva guía/remisión</label><input class="form-control" value="Se generará automáticamente" readonly></div>
    <div class="col-md-3"><label class="form-label" for="document_date">Fecha</label><input class="form-control" type="date" id="document_date" name="document_date" value="<?= esc(old('document_date', date('Y-m-d'))) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="vehicle_plate">Placa</label><input class="form-control" id="vehicle_plate" name="vehicle_plate" maxlength="20" value="<?= esc(old('vehicle_plate')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="transporter_name">Transportista</label><input class="form-control" id="transporter_name" name="transporter_name" maxlength="180" value="<?= esc(old('transporter_name')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="transporter_identifier">RUC/C.I. transportista</label><input class="form-control" id="transporter_identifier" name="transporter_identifier" maxlength="30" value="<?= esc(old('transporter_identifier')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="destination_name">Destinatario</label><input class="form-control" id="destination_name" name="destination_name" maxlength="180" value="<?= esc(old('destination_name', $dispatch['destination_name'] ?? '')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="destination_identifier">RUC/C.I. destinatario</label><input class="form-control" id="destination_identifier" name="destination_identifier" maxlength="30" value="<?= esc(old('destination_identifier', $dispatch['destination_identifier'] ?? '')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="destination_address">Dirección de destino</label><input class="form-control" id="destination_address" name="destination_address" maxlength="300" value="<?= esc(old('destination_address', $dispatch['destination_address'] ?? '')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="route_description">Ruta</label><input class="form-control" id="route_description" name="route_description" maxlength="300" value="<?= esc(old('route_description', $dispatch['route_description'] ?? '')) ?>"></div>
</div>
<hr class="my-4"><h2 class="h5">Responsables</h2><div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="delivered_by_name">Quien entrega</label><input class="form-control" id="delivered_by_name" name="delivered_by_name" maxlength="160" value="<?= esc(old('delivered_by_name')) ?>" required></div>
    <div class="col-md-4"><label class="form-label" for="received_by_name">Quien recibe</label><input class="form-control" id="received_by_name" name="received_by_name" maxlength="160" value="<?= esc(old('received_by_name')) ?>" required></div>
    <div class="col-md-4"><label class="form-label" for="received_by_area_name">Área receptora</label><input class="form-control" id="received_by_area_name" name="received_by_area_name" maxlength="160" value="<?= esc(old('received_by_area_name')) ?>" required></div>
</div>
<hr class="my-4"><h2 class="h5">Cantidades a entregar</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Material</th><th class="text-end">Solicitado</th><th class="text-end">Entregado acumulado</th><th class="text-end">Pendiente</th><th style="width: 180px">Entregar ahora</th></tr></thead><tbody>
<?php foreach ($items as $index => $item): ?><tr><td><input type="hidden" name="source_dispatch_item_id[]" value="<?= esc((string) $item['source_dispatch_item_id']) ?>"><input type="hidden" name="material_id[]" value="<?= esc((string) $item['material_id']) ?>"><span class="font-monospace"><?= esc($item['code']) ?></span> · <?= esc($item['name']) ?></td><td class="text-end"><?= esc(number_format((float) $item['requested_quantity'], 3, ',', '.')) ?> <?= esc($item['unit_symbol']) ?></td><td class="text-end"><?= esc(number_format((float) $item['delivered_quantity'], 3, ',', '.')) ?> <?= esc($item['unit_symbol']) ?></td><td class="text-end fw-semibold text-warning"><?= esc(number_format((float) $item['pending_quantity'], 3, ',', '.')) ?> <?= esc($item['unit_symbol']) ?></td><td><input class="form-control" type="number" name="quantity[]" min="0" max="<?= esc((string) $item['pending_quantity']) ?>" step="0.001" value="<?= esc(old('quantity.' . $index, (string) $item['pending_quantity'])) ?>"></td></tr><?php endforeach ?>
</tbody></table></div>
<div class="row g-3"><div class="col-12"><label class="form-label" for="reason">Motivo</label><textarea class="form-control" id="reason" name="reason" maxlength="1000" required><?= esc(old('reason', 'Entrega de cantidades pendientes')) ?></textarea></div><div class="col-12"><label class="form-label" for="observations">Observaciones</label><textarea class="form-control" id="observations" name="observations" maxlength="2000"><?= esc(old('observations')) ?></textarea></div></div>
<button class="btn btn-primary mt-4" type="submit">Confirmar entrega</button>
</form></div></div>
<?= $this->endSection() ?>
