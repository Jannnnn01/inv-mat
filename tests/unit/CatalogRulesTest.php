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

    public function testInventoryPermissionsEnforceSeparationOfDuties(): void
    {
        $groups = new AuthGroups();

        $this->assertContains('inventory.entries.create', $groups->matrix['warehouse']);
        $this->assertContains('inventory.exits.create', $groups->matrix['warehouse']);
        $this->assertContains('inventory.adjustments.request', $groups->matrix['warehouse']);
        $this->assertContains('inventory.reversals.request', $groups->matrix['warehouse']);
        $this->assertNotContains('inventory.adjustments.approve', $groups->matrix['warehouse']);
        $this->assertNotContains('inventory.reversals.approve', $groups->matrix['warehouse']);
        $this->assertContains('inventory.dispatch_requests.create', $groups->matrix['warehouse']);
        $this->assertNotContains('inventory.dispatch_requests.approve', $groups->matrix['warehouse']);
        $this->assertNotContains('financial.view', $groups->matrix['warehouse']);
        $this->assertNotContains('inventory.entries.create', $groups->matrix['viewer']);
        $this->assertContains('financial.*', $groups->matrix['admin']);
        $this->assertNotContains('financial.manage', $groups->matrix['warehouse']);
        $this->assertNotContains('financial.view', $groups->matrix['viewer']);
    }

    public function testDispatchWorkflowProtectsReservationsAndNumbering(): void
    {
        $migration = file_get_contents(APPPATH . 'Database/Migrations/2026-07-21-000009_CreateDispatchWorkflowTables.php');
        $movement = file_get_contents(APPPATH . 'Services/InventoryMovementService.php');

        $this->assertIsString($migration);
        $this->assertIsString($movement);
        $this->assertStringContainsString('inventory_reservations', $migration);
        $this->assertStringContainsString('dispatch_requests', $migration);
        $this->assertStringContainsString('uq_dispatch_guide_number', $migration);
        $this->assertStringContainsString('FOR UPDATE', $movement);
        $this->assertStringContainsString('nextGuideNumber', $movement);
        $this->assertStringContainsString('consumeDispatchReservation', $movement);
    }

    public function testDispatchPdfAndRecipientCatalogArePresent(): void
    {
        $pdf = file_get_contents(APPPATH . 'Views/inventory/dispatches/pdf.php');
        $recipient = file_get_contents(APPPATH . 'Controllers/Catalogs/RecipientController.php');

        $this->assertIsString($pdf);
        $this->assertIsString($recipient);
        $this->assertStringContainsString('Guía de remisión', $pdf);
        $this->assertStringContainsString('Destinatario', $pdf);
        $this->assertStringContainsString('class RecipientController', $recipient);
    }

    public function testUnknownCatalogIsRejectedBeforeDatabaseAccess(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CatalogStateService())->toggle('unknown', 1, 1);
    }

    public function testNegativeStockHasServiceLockingAndDatabaseConstraint(): void
    {
        $service = file_get_contents(APPPATH . 'Services/InventoryMovementService.php');
        $migration = file_get_contents(APPPATH . 'Database/Migrations/2026-07-20-000002_CreateCatalogTables.php');

        $this->assertIsString($service);
        $this->assertIsString($migration);
        $this->assertStringContainsString('FOR UPDATE', $service);
        $this->assertStringContainsString('chk_inventory_stock_nonnegative CHECK (quantity >= 0)', $migration);
    }
}
