<?= $this->extend('layouts/app') ?>
<?= $this->section('title') ?><?= $record ? 'Editar destinatario' : 'Nuevo destinatario' ?><?= $this->endSection() ?>
<?= $this->section('main') ?>
<div class="card border-0 shadow-sm mx-auto app-wide-form-card"><div class="card-body p-4"><div class="d-flex justify-content-between mb-4"><div><p class="app-eyebrow mb-1">Catálogos</p><h1 class="h3 mb-0"><?= $record ? 'Editar destinatario' : 'Nuevo destinatario' ?></h1></div><a class="btn btn-outline-secondary" href="<?= url_to('recipients') ?>">Volver</a></div>
<?= $this->include('partials/flash') ?>
<form method="post" action="<?= $record ? url_to('recipients-update', $record['id']) : url_to('recipients-create') ?>"><?= csrf_field() ?><div class="row g-3">
<div class="col-md-8"><label class="form-label" for="name">Nombre o razón social</label><input class="form-control" id="name" name="name" maxlength="180" value="<?= esc(old('name', $record['name'] ?? '')) ?>" required></div>
<div class="col-md-2"><label class="form-label" for="document_type">Tipo documento</label><input class="form-control" id="document_type" name="document_type" maxlength="30" value="<?= esc(old('document_type', $record['document_type'] ?? '')) ?>"></div>
<div class="col-md-2"><label class="form-label" for="document_number">Número</label><input class="form-control" id="document_number" name="document_number" maxlength="50" value="<?= esc(old('document_number', $record['document_number'] ?? '')) ?>"></div>
<div class="col-md-8"><label class="form-label" for="address">Dirección</label><input class="form-control" id="address" name="address" maxlength="300" value="<?= esc(old('address', $record['address'] ?? '')) ?>" required></div>
<div class="col-md-4"><label class="form-label" for="route">Ruta</label><input class="form-control" id="route" name="route" maxlength="300" value="<?= esc(old('route', $record['route'] ?? '')) ?>"></div>
<div class="col-md-4"><label class="form-label" for="contact_name">Contacto</label><input class="form-control" id="contact_name" name="contact_name" maxlength="120" value="<?= esc(old('contact_name', $record['contact_name'] ?? '')) ?>"></div>
<div class="col-md-4"><label class="form-label" for="email">Correo</label><input class="form-control" type="email" id="email" name="email" maxlength="254" value="<?= esc(old('email', $record['email'] ?? '')) ?>"></div>
<div class="col-md-4"><label class="form-label" for="phone">Teléfono</label><input class="form-control" id="phone" name="phone" maxlength="40" value="<?= esc(old('phone', $record['phone'] ?? '')) ?>"></div>
</div><button class="btn btn-primary mt-4">Guardar destinatario</button></form></div></div>
<?= $this->endSection() ?>
