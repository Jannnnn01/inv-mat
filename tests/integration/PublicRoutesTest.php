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

    public function testHomeRouteRedirectsVisitorsToLogin(): void
    {
        $result = $this->get('/');

        $result->assertRedirectTo('/login');
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

    public function testPublicRegistrationRouteDoesNotExist(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get('/register');
    }

    public function testLogoutCannotBeRequestedWithGet(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get('/logout');
    }

    public function testCatalogsHaveNoPhysicalDeleteEndpoint(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->delete('/catalogos/materiales/1');
    }

    public function testInventoryHistoryHasNoDeleteEndpoint(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->delete('/inventario/movimientos/1');
    }

    public function testValuationEventsHaveNoDeleteEndpoint(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->delete('/inventario/valoraciones/1');
    }

    public function testAttachmentsHaveNoPhysicalDeleteEndpoint(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->delete('/inventario/archivos/1');
    }

    public function testDispatchesHaveNoPhysicalDeleteEndpoint(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->delete('/inventario/despachos/1');
    }

    public function testAuditEventsHaveNoMutationEndpoint(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->delete('/admin/auditoria/1');
    }
}
