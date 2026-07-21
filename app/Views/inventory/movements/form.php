<?= $this->extend('layouts/app') ?>
<?php $isEntry = $type === 'ENTRY'; ?>
<?= $this->section('title') ?><?= $isEntry ? 'Nueva entrada' : 'Nueva salida' ?><?= $this->endSection() ?>
<?= $this->section('main') ?>
<div class="card border-0 shadow-sm mx-auto app-wide-form-card"><div class="card-body p-4">
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h4 mb-1"><?= $isEntry ? 'Registrar entrada' : 'Registrar salida' ?></h1><p class="text-body-secondary mb-0">La operación se confirmará de forma atómica y no podrá editarse.</p></div><a class="btn btn-sm btn-outline-secondary" href="<?= url_to('inventory-movements') ?>">Volver</a></div>
<?= $this->include('partials/flash') ?>
<?php if ($materials === [] || $warehouses === []): ?><div class="alert alert-warning">Necesitas al menos una bodega y un material activos.</div><?php endif ?>
<form method="post" action="<?= $isEntry ? url_to('inventory-entry-create') : url_to('inventory-exit-create') ?>" data-inventory-form><?= csrf_field() ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="warehouse_id">Bodega</label><select class="form-select" id="warehouse_id" name="warehouse_id" required><option value="">Selecciona...</option><?php foreach ($warehouses as $warehouse): ?><option value="<?= esc((string) $warehouse['id']) ?>" <?= (string) old('warehouse_id') === (string) $warehouse['id'] ? 'selected' : '' ?>><?= esc($warehouse['name']) ?></option><?php endforeach ?></select></div>
    <?php if ($isEntry): ?><div class="col-md-6"><label class="form-label" for="supplier_id">Proveedor o donante (opcional)</label><select class="form-select" id="supplier_id" name="supplier_id"><option value="">Sin proveedor</option><?php foreach ($suppliers as $supplier): ?><option value="<?= esc((string) $supplier['id']) ?>" <?= (string) old('supplier_id') === (string) $supplier['id'] ? 'selected' : '' ?>><?= esc($supplier['name']) ?></option><?php endforeach ?></select></div><?php endif ?>
    <div class="col-md-6"><label class="form-label" for="document_number"><?= $isEntry ? 'Factura o documento' : 'Número de guía o remisión' ?> (opcional)</label><input class="form-control" id="document_number" name="document_number" maxlength="80" value="<?= esc(old('document_number')) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="document_date">Fecha del documento</label><input class="form-control" type="date" id="document_date" name="document_date" value="<?= esc(old('document_date')) ?>"></div>
    <?php if ($isEntry): ?><div class="col-md-3"><label class="form-label" for="purchase_order_number">Orden de compra</label><input class="form-control" id="purchase_order_number" name="purchase_order_number" maxlength="80" value="<?= esc(old('purchase_order_number')) ?>"></div><?php endif ?>
    <?php if (! $isEntry): ?><div class="col-12"><label class="form-label" for="reason">Motivo</label><textarea class="form-control" id="reason" name="reason" maxlength="1000" required><?= esc(old('reason')) ?></textarea></div><?php endif ?>
</div>
<?php if (! $isEntry): ?>
<hr class="my-4"><h2 class="h5">Información de traslado</h2><p class="text-body-secondary">Completa los datos disponibles en la guía física. Los campos son opcionales cuando el documento no los incluya.</p>
<div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="authorization_number">Autorización</label><input class="form-control" id="authorization_number" name="authorization_number" maxlength="100" value="<?= esc(old('authorization_number')) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="start_date">Fecha de inicio</label><input class="form-control" type="date" id="start_date" name="start_date" value="<?= esc(old('start_date')) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="end_date">Fecha de fin</label><input class="form-control" type="date" id="end_date" name="end_date" value="<?= esc(old('end_date')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="issuer_name">Emisor</label><input class="form-control" id="issuer_name" name="issuer_name" maxlength="180" value="<?= esc(old('issuer_name')) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="issuer_tax_identifier">RUC/C.I. emisor</label><input class="form-control" id="issuer_tax_identifier" name="issuer_tax_identifier" maxlength="30" value="<?= esc(old('issuer_tax_identifier')) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="origin_place">Partida</label><input class="form-control" id="origin_place" name="origin_place" maxlength="180" value="<?= esc(old('origin_place')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="transporter_name">Transportista</label><input class="form-control" id="transporter_name" name="transporter_name" maxlength="180" value="<?= esc(old('transporter_name')) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="transporter_identifier">RUC/C.I. transportista</label><input class="form-control" id="transporter_identifier" name="transporter_identifier" maxlength="30" value="<?= esc(old('transporter_identifier')) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="vehicle_plate">Placa</label><input class="form-control" id="vehicle_plate" name="vehicle_plate" maxlength="20" value="<?= esc(old('vehicle_plate')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="destination_name">Destinatario</label><input class="form-control" id="destination_name" name="destination_name" maxlength="180" value="<?= esc(old('destination_name')) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="destination_identifier">RUC/C.I. destinatario</label><input class="form-control" id="destination_identifier" name="destination_identifier" maxlength="30" value="<?= esc(old('destination_identifier')) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="related_document_number">Documento relacionado</label><input class="form-control" id="related_document_number" name="related_document_number" maxlength="100" value="<?= esc(old('related_document_number')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="destination_address">Dirección de destino</label><input class="form-control" id="destination_address" name="destination_address" maxlength="300" value="<?= esc(old('destination_address')) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="route_description">Ruta</label><input class="form-control" id="route_description" name="route_description" maxlength="300" value="<?= esc(old('route_description')) ?>"></div>
    <div class="col-12"><label class="form-label" for="dispatch_description">Descripción</label><textarea class="form-control" id="dispatch_description" name="dispatch_description" maxlength="2000" rows="2"><?= esc(old('dispatch_description')) ?></textarea></div>
</div>
<?php endif ?>
<hr class="my-4"><h2 class="h5">Responsables</h2><div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="delivered_by_name">Quien entrega</label><input class="form-control" id="delivered_by_name" name="delivered_by_name" maxlength="160" value="<?= esc(old('delivered_by_name')) ?>" required></div>
    <div class="col-md-6"><label class="form-label" for="received_by_name">Quien recibe</label><input class="form-control" id="received_by_name" name="received_by_name" maxlength="160" value="<?= esc(old('received_by_name')) ?>" required></div>
    <div class="col-md-4"><label class="form-label" for="delivered_by_identification">Identificación de quien entrega</label><input class="form-control" id="delivered_by_identification" name="delivered_by_identification" maxlength="60" value="<?= esc(old('delivered_by_identification')) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="delivered_by_position">Cargo de quien entrega</label><input class="form-control" id="delivered_by_position" name="delivered_by_position" maxlength="120" value="<?= esc(old('delivered_by_position')) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="delivered_by_area_name">Área de quien entrega</label><input class="form-control" id="delivered_by_area_name" name="delivered_by_area_name" maxlength="160" value="<?= esc(old('delivered_by_area_name')) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="received_by_identification">Identificación de quien recibe</label><input class="form-control" id="received_by_identification" name="received_by_identification" maxlength="60" value="<?= esc(old('received_by_identification')) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="received_by_position">Cargo de quien recibe</label><input class="form-control" id="received_by_position" name="received_by_position" maxlength="120" value="<?= esc(old('received_by_position')) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="received_by_area_name"><?= $isEntry ? 'Área de quien recibe' : 'Área receptora' ?></label><input class="form-control" id="received_by_area_name" name="received_by_area_name" maxlength="160" value="<?= esc(old('received_by_area_name')) ?>" <?= $isEntry ? '' : 'required' ?>></div>
</div>
<hr class="my-4"><div class="d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Materiales</h2><button class="btn btn-sm btn-outline-primary" type="button" data-add-inventory-row>Agregar material</button></div>
<?php if (! $isEntry): ?><div class="alert alert-info mt-3">La existencia disminuye únicamente por la cantidad entregada. Escribe <strong>0</strong> cuando una línea quede totalmente pendiente.</div><?php endif ?>
<div class="mt-3" data-inventory-items>
    <div class="row g-2 align-items-end inventory-item-row mb-3">
        <div class="col-lg-<?= $isEntry ? '4' : '5' ?>"><label class="form-label">Material</label><select class="form-select" name="material_id[]" required><option value="">Selecciona...</option><?php foreach ($materials as $material): ?><option value="<?= esc((string) $material['id']) ?>"><?= esc($material['code'] . ' - ' . $material['name'] . ' (' . $material['unit_symbol'] . ')') ?></option><?php endforeach ?></select></div>
        <?php if (! $isEntry): ?><div class="col-lg-3"><label class="form-label">Cantidad solicitada</label><input class="form-control" type="number" name="requested_quantity[]" min="0.001" max="99999999999.999" step="0.001" required></div><?php endif ?>
        <div class="col-lg-2"><label class="form-label"><?= $isEntry ? 'Cantidad' : 'Cantidad entregada' ?></label><input class="form-control" type="number" name="quantity[]" min="<?= $isEntry ? '0.001' : '0' ?>" max="99999999999.999" step="0.001" required></div>
        <?php if ($isEntry): ?><div class="col-lg-2"><label class="form-label">Costo unitario</label><input class="form-control" type="number" name="unit_cost[]" min="0.000001" max="999999999999.999999" step="0.000001"></div><div class="col-lg-3"><label class="form-label">Motivo sin costo</label><input class="form-control" name="no_cost_reason[]" maxlength="500" placeholder="Obligatorio si no hay costo"></div><?php endif ?>
        <div class="col-lg-1"><button class="btn btn-outline-danger w-100" type="button" data-remove-inventory-row aria-label="Quitar material">×</button></div>
    </div>
</div>
<div class="mb-3"><label class="form-label" for="observations">Observaciones</label><textarea class="form-control" id="observations" name="observations" rows="3" maxlength="2000"><?= esc(old('observations')) ?></textarea></div>
<button class="btn <?= $isEntry ? 'btn-success' : 'btn-primary' ?>" type="submit" <?= $materials === [] || $warehouses === [] ? 'disabled' : '' ?>>Confirmar <?= $isEntry ? 'entrada' : 'salida' ?></button>
</form></div></div>
<?= $this->endSection() ?>
