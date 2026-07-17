<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Database;
use Config\Routing;
use Config\Security;

/**
 * @internal
 */
final class ConfigurationTest extends CIUnitTestCase
{
    public function testSecureBaselineConfiguration(): void
    {
        $app = new App();
        $routing = new Routing();
        $security = new Security();

        $this->assertFalse($routing->autoRoute);
        $this->assertTrue($app->CSPEnabled);
        $this->assertSame('es', $app->defaultLocale);
        $this->assertSame('America/Bogota', $app->appTimezone);
        $this->assertSame('session', $security->csrfProtection);
        $this->assertTrue($security->tokenRandomize);
    }

    public function testPostgreSqlIsTheConfiguredDatabase(): void
    {
        $database = new Database();

        $this->assertSame('Postgre', $database->default['DBDriver']);
        $this->assertSame(5432, $database->default['port']);
        $this->assertSame('Postgre', $database->tests['DBDriver']);
        $this->assertSame(5432, $database->tests['port']);
    }
}
