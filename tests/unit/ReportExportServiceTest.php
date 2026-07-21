<?php

use App\Services\ReportExportService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ReportExportServiceTest extends CIUnitTestCase
{
    public function testCsvUsesDeclaredColumnOrderAndUtf8Bom(): void
    {
        $csv = (new ReportExportService())->csv([
            'columns' => ['code' => 'Código', 'name' => 'Material'],
            'rows'    => [['name' => 'Cartulina', 'code' => 'MAT-01']],
        ]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("Código,Material", $csv);
        $this->assertStringContainsString("MAT-01,Cartulina", $csv);
    }

    public function testPdfExportProducesAPdfDocument(): void
    {
        $pdf = (new ReportExportService())->pdf([
            'report' => [
                'title'       => 'Existencias actuales',
                'description' => 'Saldo físico por material y bodega.',
                'columns'     => ['code' => 'Código', 'name' => 'Material'],
                'rows'        => [['code' => 'MAT-01', 'name' => 'Cartulina']],
                'message'     => null,
            ],
            'filterSummary' => ['Bodega: Principal'],
            'generatedAt'   => '2026-07-21 11:00:00',
        ]);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));
    }

    /** @dataProvider dangerousValues */
    public function testCsvFormulaInjectionIsNeutralized(string $value): void
    {
        $this->assertSame("'" . $value, (new ReportExportService())->safeCsvValue($value));
    }

    /** @return array<string, array{string}> */
    public static function dangerousValues(): array
    {
        return [
            'formula' => ['=2+2'],
            'command' => ['+cmd|calc'],
            'negative formula' => ['-10+20'],
            'external value' => ['@SUM(A1:A2)'],
            'leading whitespace' => ["\t=HYPERLINK(\"https://invalid.example\")"],
        ];
    }
}
