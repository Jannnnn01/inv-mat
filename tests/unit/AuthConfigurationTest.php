<?php

use App\Contracts\AuthMailerInterface;
use App\Filters\ActiveUserFilter;
use App\Services\MagicLinkService;
use App\Services\UserAccountService;
use App\Services\UserRoleService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Auth;
use Config\AuthGroups;
use Config\Filters;
use Config\Services;

/**
 * @internal
 */
final class AuthConfigurationTest extends CIUnitTestCase
{
    public function testPublicRegistrationAndRememberMeAreDisabled(): void
    {
        $auth = new Auth();

        $this->assertFalse($auth->allowRegistration);
        $this->assertFalse($auth->sessionConfig['allowRemembering']);
        $this->assertTrue($auth->allowMagicLinkLogins);
        $this->assertSame(15 * MINUTE, $auth->magicLinkLifetime);
        $this->assertSame(12, $auth->minimumPasswordLength);
        $this->assertSame('\App\Views\auth\login', $auth->views['login']);
        $this->assertSame('\App\Views\auth\magic_link_form', $auth->views['magic-link-login']);
    }

    public function testInitialRolesHaveExpectedBoundaries(): void
    {
        $groups = new AuthGroups();

        $this->assertSame(['admin', 'warehouse', 'viewer'], array_keys($groups->groups));
        $this->assertContains('users.*', $groups->matrix['admin']);
        $this->assertContains('roles.*', $groups->matrix['admin']);
        $this->assertNotContains('users.manage', $groups->matrix['warehouse']);
        $this->assertNotContains('roles.assign', $groups->matrix['viewer']);
        $this->assertContains('inventory.adjustments.request', $groups->matrix['warehouse']);
        $this->assertNotContains('inventory.adjustments.approve', $groups->matrix['warehouse']);
    }

    public function testAuthenticationServicesAndActiveFilterAreRegistered(): void
    {
        $filters = new Filters();

        $this->assertSame(ActiveUserFilter::class, $filters->aliases['active-user']);
        $this->assertInstanceOf(AuthMailerInterface::class, Services::authMailer());
        $this->assertInstanceOf(MagicLinkService::class, Services::magicLinks());
        $this->assertInstanceOf(UserAccountService::class, Services::userAccounts());
    }

    public function testUnsupportedPrimaryRoleIsRejectedBeforeDatabaseAccess(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new UserRoleService())->assignPrimaryRole(
            new \CodeIgniter\Shield\Entities\User(['id' => 99]),
            'superadmin',
        );
    }
}
