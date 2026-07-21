<?php

use App\Filters\AuditFilter;
use CodeIgniter\Test\CIUnitTestCase;
use Config\AuthGroups;
use Config\Filters;

/**
 * @internal
 */
final class AuditConfigurationTest extends CIUnitTestCase
{
    public function testAuditFilterIsGlobalBeforeAndAfter(): void
    {
        $filters = new Filters();

        $this->assertSame(AuditFilter::class, $filters->aliases['audit']);
        $this->assertContains('audit', $filters->globals['before']);
        $this->assertContains('audit', $filters->globals['after']);
    }

    public function testOnlyAdministratorsReceiveAuditPermissions(): void
    {
        $groups = new AuthGroups();

        $this->assertContains('audit.*', $groups->matrix['admin']);
        $this->assertNotContains('audit.view', $groups->matrix['warehouse']);
        $this->assertNotContains('audit.view', $groups->matrix['viewer']);
    }

    public function testAuditMigrationEnforcesImmutabilityAndRetention(): void
    {
        $migration = file_get_contents(APPPATH . 'Database/Migrations/2026-07-21-000007_CreateAuditEventsTable.php');

        $this->assertIsString($migration);
        $this->assertStringContainsString('BEFORE UPDATE OR DELETE ON audit_events', $migration);
        $this->assertStringContainsString("INTERVAL '2 years'", $migration);
    }
}
