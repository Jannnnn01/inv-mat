<?php

use App\Services\CatalogStateService;
use App\Services\QuantityService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\AuthGroups;

/**
 * @internal
 */
final class CatalogRulesTest extends CIUnitTestCase
{
    public function testFractionalQuantitiesUseAtMostThreeDecimals(): void
    {
        $service = new QuantityService();

        $this->assertTrue($service->isValid('10', true));
        $this->assertTrue($service->isValid('10.125', true));
        $this->assertFalse($service->isValid('10.1254', true));
        $this->assertFalse($service->isValid('-1', true));
    }

    public function testNonFractionalMaterialsOnlyAcceptWholeQuantities(): void
    {
        $service = new QuantityService();

        $this->assertTrue($service->isValid('12', false));
        $this->assertTrue($service->isValid('12.000', false));
        $this->assertFalse($service->isValid('12.001', false));
    }

    public function testWarehouseRoleCannotDeactivateCatalogHistory(): void
    {
        $groups = new AuthGroups();

        $this->assertContains('materials.create', $groups->matrix['warehouse']);
        $this->assertContains('materials.update', $groups->matrix['warehouse']);
        $this->assertNotContains('materials.deactivate', $groups->matrix['warehouse']);
        $this->assertNotContains('categories.deactivate', $groups->matrix['warehouse']);
        $this->assertNotContains('suppliers.deactivate', $groups->matrix['warehouse']);
        $this->assertContains('units.view', $groups->matrix['viewer']);
    }

    public function testUnknownCatalogIsRejectedBeforeDatabaseAccess(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CatalogStateService())->toggle('unknown', 1, 1);
    }
}
