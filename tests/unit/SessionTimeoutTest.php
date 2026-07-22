<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\Session;

/**
 * @internal
 */
final class SessionTimeoutTest extends CIUnitTestCase
{
    public function testSessionExpiresAfterFiveMinutes(): void
    {
        $this->assertSame(300, (new Session())->expiration);
    }

    public function testAuthenticatedLayoutKeepsOnlyActiveSessionsAlive(): void
    {
        $layout = file_get_contents(APPPATH . 'Views/layouts/app.php');
        $script = file_get_contents(ROOTPATH . 'resources/js/app.js');
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertIsString($layout);
        $this->assertIsString($script);
        $this->assertIsString($routes);
        $this->assertStringContainsString('data-session-idle-seconds="300"', $layout);
        $this->assertStringContainsString('data-session-logout-form', $layout);
        $this->assertStringContainsString("['keydown', 'pointerdown', 'scroll', 'touchstart']", $script);
        $this->assertStringContainsString('window.setInterval(keepActiveSession, 60_000)', $script);
        $this->assertStringContainsString("'filter' => ['session', 'active-user']", $routes);
    }
}
