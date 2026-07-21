<?= $this->extend('layouts/app') ?>

<?= $this->section('title') ?>Reportes<?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
$movementLabels = ['ENTRY' => 'Entrada', 'EXIT' => 'Salida', 'ADJUSTMENT' => 'Ajuste', 'REVERSAL' => 'Reversión'];
$resultLabels = ['SUCCESS' => 'Exitoso', 'FAILURE' => 'Fallido', 'DENIED' => 'Denegado'];
$numberKeys = [
    'quantity', 'minimum_stock', 'deficit', 'valued_quantity', 'average_unit_cost', 'total_value',
    'unit_cost', 'line_value', 'stock_before', 'stock_after', 'total_quantity', 'movements',
    'total_movements', 'entries', 'exits', 'adjustments', 'reversals', 'total_lines',
];
$moneyKeys = ['average_unit_cost', 'total_value', 'unit_cost', 'line_value'];
$displayValue = static function (string $key, mixed $value) use ($numberKeys, $moneyKeys): string {
    if ($value === null || $value === '') {
        return '—';
    }
    if (in_array($key, $numberKeys, true) && is_numeric($value)) {
        $decimals = in_array($key, $moneyKeys, true) ? 2 : 3;
        if (in_array($key, ['movements', 'total_movements', 'entries', 'exits', 'adjustments', 'reversals', 'total_lines'], true)) {
            $decimals = 0;
        }
        return number_format((float) $value, $decimals, ',', '.');
    }
    return (string) $value;
};
?>
<section class="app-page-section" aria-labelledby="reports-title">
    <div class="app-page-header">
        <div>
            <p class="app-eyebrow mb-1">Análisis y control</p>
            <h1 class="h2 mb-1" id="reports-title">Reportes</h1>
            <p class="app-page-description mb-0">Consulta, filtra y exporta la información operativa del inventario.</p>
        </div>
        <?php if ($canExport): ?>
            <div class="app-page-actions" aria-label="Opciones de exportación">
                <a class="btn btn-outline-success" href="<?= url_to('reports-csv', $report['key']) ?><?= $queryString !== '' ? '?' . esc($queryString, 'attr') : '' ?>">Descargar CSV</a>
                <a class="btn btn-primary" href="<?= url_to('reports-pdf', $report['key']) ?><?= $queryString !== '' ? '?' . esc($queryString, 'attr') : '' ?>">Descargar PDF</a>
            </div>
        <?php endif ?>
    </div>

    <?= $this->include('partials/flash') ?>

    <nav class="report-selector" aria-label="Tipos de reporte">
        <?php foreach ($definitions as $key => $definition): ?>
            <a class="report-selector-item<?= $report['key'] === $key ? ' is-active' : '' ?>" href="<?= url_to('reports') ?>?report=<?= esc($key, 'url') ?>"<?= $report['key'] === $key ? ' aria-current="page"' : '' ?>>
                <span class="report-selector-mark" aria-hidden="true"></span>
                <span><strong><?= esc($definition['title']) ?></strong><small><?= esc($definition['description']) ?></small></span>
            </a>
        <?php endforeach ?>
    </nav>

    <form class="card report-filter-card" method="get" action="<?= url_to('reports') ?>">
        <input type="hidden" name="report" value="<?= esc($report['key']) ?>">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div><h2 class="h5 mb-1">Filtros</h2><p class="small text-body-secondary mb-0">Los filtros disponibles se aplican cuando corresponden al reporte seleccionado.</p></div>
            </div>
            <div class="row g-3">
                <div class="col-sm-6 col-xl-2"><label class="form-label" for="from">Desde</label><input class="form-control" id="from" name="from" type="date" value="<?= esc($filters['from']) ?>"></div>
                <div class="col-sm-6 col-xl-2"><label class="form-label" for="to">Hasta</label><input class="form-control" id="to" name="to" type="date" value="<?= esc($filters['to']) ?>"></div>
                <div class="col-md-6 col-xl-2"><label class="form-label" for="material_id">Material</label><select class="form-select" id="material_id" name="material_id"><option value="">Todos</option><?php foreach ($lookups['materials'] as $material): ?><option value="<?= (int) $material['id'] ?>" <?= $filters['material_id'] === (int) $material['id'] ? 'selected' : '' ?>><?= esc($material['code'] . ' · ' . $material['name']) ?></option><?php endforeach ?></select></div>
                <div class="col-md-6 col-xl-2"><label class="form-label" for="category_id">Categoría</label><select class="form-select" id="category_id" name="category_id"><option value="">Todas</option><?php foreach ($lookups['categories'] as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= esc($category['name']) ?></option><?php endforeach ?></select></div>
                <div class="col-md-6 col-xl-2"><label class="form-label" for="warehouse_id">Bodega</label><select class="form-select" id="warehouse_id" name="warehouse_id"><option value="">Todas</option><?php foreach ($lookups['warehouses'] as $warehouse): ?><option value="<?= (int) $warehouse['id'] ?>" <?= $filters['warehouse_id'] === (int) $warehouse['id'] ? 'selected' : '' ?>><?= esc($warehouse['name']) ?></option><?php endforeach ?></select></div>
                <div class="col-md-6 col-xl-2"><label class="form-label" for="user_id">Usuario</label><select class="form-select" id="user_id" name="user_id"><option value="">Todos</option><?php foreach ($lookups['users'] as $user): ?><option value="<?= (int) $user->id ?>" <?= $filters['user_id'] === (int) $user->id ? 'selected' : '' ?>><?= esc($user->username) ?></option><?php endforeach ?></select></div>
                <div class="col-md-6 col-xl-2"><label class="form-label" for="movement_type">Tipo de movimiento</label><select class="form-select" id="movement_type" name="movement_type"><option value="">Todos</option><?php foreach ($movementLabels as $value => $label): ?><option value="<?= $value ?>" <?= $filters['movement_type'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?></select></div>
                <?php if ($report['key'] === 'administrative-audit'): ?>
                    <div class="col-md-6 col-xl-2"><label class="form-label" for="audit_module">Módulo</label><select class="form-select" id="audit_module" name="audit_module"><option value="">Todos</option><?php foreach ($lookups['auditModules'] as $module): ?><option value="<?= esc($module) ?>" <?= $filters['audit_module'] === $module ? 'selected' : '' ?>><?= esc(ucfirst($module)) ?></option><?php endforeach ?></select></div>
                    <div class="col-md-6 col-xl-2"><label class="form-label" for="audit_result">Resultado</label><select class="form-select" id="audit_result" name="audit_result"><option value="">Todos</option><?php foreach ($resultLabels as $value => $label): ?><option value="<?= $value ?>" <?= $filters['audit_result'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?></select></div>
                    <div class="col-md-6 col-xl-2"><label class="form-label" for="action">Acción</label><input class="form-control" id="action" name="action" maxlength="120" value="<?= esc($filters['action']) ?>" placeholder="Ej. reportes"></div>
                <?php endif ?>
            </div>
            <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                <a class="btn btn-outline-secondary" href="<?= url_to('reports') ?>?report=<?= esc($report['key'], 'url') ?>">Limpiar</a>
                <button class="btn btn-primary" type="submit">Aplicar filtros</button>
            </div>
        </div>
    </form>

    <article class="card app-table-card report-result" aria-labelledby="report-result-title">
        <div class="card-header report-result-header">
            <div><p class="app-eyebrow mb-1">Resultado</p><h2 class="h4 mb-1" id="report-result-title"><?= esc($report['title']) ?></h2><p class="small text-body-secondary mb-0"><?= esc($report['description']) ?></p></div>
            <span class="badge rounded-pill text-bg-light border"><?= count($report['rows']) ?> registro<?= count($report['rows']) === 1 ? '' : 's' ?></span>
        </div>
        <?php if ($report['message'] !== null): ?><div class="alert alert-info m-3 mb-0" role="status"><?= esc($report['message']) ?></div><?php endif ?>
        <?php if ($report['limited']): ?><div class="alert alert-warning m-3 mb-0" role="status">Se muestran los primeros 500 registros. Usa filtros más específicos para acotar el resultado.</div><?php endif ?>
        <div class="app-table-responsive">
            <table class="table app-data-table align-middle mb-0">
                <caption class="visually-hidden"><?= esc($report['title']) ?></caption>
                <thead><tr><?php foreach ($report['columns'] as $label): ?><th scope="col"><?= esc($label) ?></th><?php endforeach ?></tr></thead>
                <tbody>
                    <?php foreach ($report['rows'] as $row): ?><tr><?php foreach ($report['columns'] as $key => $label): ?><td data-label="<?= esc($label, 'attr') ?>"><?= esc($displayValue($key, $row[$key] ?? null)) ?></td><?php endforeach ?></tr><?php endforeach ?>
                    <?php if ($report['rows'] === [] && $report['message'] === null): ?><tr><td class="app-empty-state" colspan="<?= count($report['columns']) ?>">No hay información que coincida con los filtros seleccionados.</td></tr><?php endif ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?= $this->endSection() ?>
