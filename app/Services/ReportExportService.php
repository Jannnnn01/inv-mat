<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class ReportExportService
{
    /**
     * @param array{columns: array<string, string>, rows: list<array<string, mixed>>} $report
     */
    public function csv(array $report): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('No fue posible preparar el archivo CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_values($report['columns']));
        foreach ($report['rows'] as $row) {
            $values = [];
            foreach (array_keys($report['columns']) as $key) {
                $values[] = $this->safeCsvValue($row[$key] ?? null);
            }
            fputcsv($stream, $values);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        if ($content === false) {
            throw new RuntimeException('No fue posible generar el archivo CSV.');
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $viewData
     */
    public function pdf(array $viewData): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reports/pdf', $viewData), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @param mixed $value */
    public function safeCsvValue($value): string
    {
        if ($value === null) {
            return '';
        }

        $text = is_bool($value) ? ($value ? 'Sí' : 'No') : (string) $value;
        if (preg_match('/\A[\s\x{0000}-\x{001F}]*[=+\-@]/u', $text) === 1) {
            return "'" . $text;
        }

        return $text;
    }
}
