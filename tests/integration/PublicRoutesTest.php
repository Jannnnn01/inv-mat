<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * @internal
 */
final class PublicRoutesTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testHomeRouteIsAvailable(): void
    {
        $result = $this->get('/');

        $result->assertOK();
        $result->assertSee('Inventario de materiales');
    }

    public function testHealthRouteReturnsOnlyOperationalStatus(): void
    {
        $result = $this->get('/health');

        $result->assertOK();
        $result->assertJSONFragment(['status' => 'ok']);
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    public function testUndeclaredRouteIsNotResolvedAutomatically(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get('/controller-not-declared');
    }
}
