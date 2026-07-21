<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= esc($report['title']) ?></title>
    <style>
        @page { margin: 28px 30px 38px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { margin: 0 0 4px; color: #10243b; font-size: 18px; }
        p { margin: 0; }
        .meta { margin-bottom: 14px; color: #536174; }
        .filters { margin: 10px 0 12px; padding: 8px 10px; background: #f1f5f9; border-radius: 4px; }
        .filters span { display: inline-block; margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 6px 5px; background: #173d67; color: #fff; font-size: 7px; text-align: left; text-transform: uppercase; }
        td { padding: 5px; border-bottom: 1px solid #dbe3ec; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .empty { padding: 20px; color: #64748b; text-align: center; }
        footer { position: fixed; right: 0; bottom: -24px; left: 0; color: #64748b; font-size: 7px; text-align: right; }
    </style>
</head>
<body>
    <h1><?= esc($report['title']) ?></h1>
    <p class="meta"><?= esc($report['description']) ?> · Generado: <?= esc($generatedAt) ?> · Registros: <?= count($report['rows']) ?></p>
    <?php if ($filterSummary !== []): ?><div class="filters"><strong>Filtros:</strong> <?php foreach ($filterSummary as $filter): ?><span><?= esc($filter) ?></span><?php endforeach ?></div><?php endif ?>
    <table>
        <thead><tr><?php foreach ($report['columns'] as $label): ?><th><?= esc($label) ?></th><?php endforeach ?></tr></thead>
        <tbody>
            <?php foreach ($report['rows'] as $row): ?><tr><?php foreach ($report['columns'] as $key => $label): ?><td><?= esc($row[$key] ?? '—') ?></td><?php endforeach ?></tr><?php endforeach ?>
            <?php if ($report['rows'] === []): ?><tr><td class="empty" colspan="<?= count($report['columns']) ?>"><?= esc($report['message'] ?? 'No hay información para los filtros seleccionados.') ?></td></tr><?php endif ?>
        </tbody>
    </table>
    <footer>Sistema institucional de inventario · Documento generado automáticamente</footer>
</body>
</html>
