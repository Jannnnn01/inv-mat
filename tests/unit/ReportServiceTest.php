<?php

use App\Services\ReportService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ReportServiceTest extends CIUnitTestCase
{
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
