<?php

use App\Services\ReportService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ReportServiceTest extends CIUnitTestCase
{
    public function testStockReportsUseTheReservationColumnCreatedByTheMigration(): void
    {
        $source = file_get_contents(APPPATH . 'Services/ReportService.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('SUM(remaining_quantity)', $source);
        $this->assertStringNotContainsString('quantity_remaining', $source);
    }

    public function testInitialReportCatalogIsComplete(): void
    {
        $definitions = (new ReportService())->definitions();

        $this->assertSame([
            'current-stock',
            'low-stock',
            'kardex',
            'entries',
            'exits',
            'adjustments',
            'movements-by-user',
            'most-used-materials',
            'administrative-audit',
        ], array_keys($definitions));
    }

    public function testUnknownReportDoesNotExist(): void
    {
        $this->assertFalse((new ReportService())->exists('invented-report'));
    }
}
