<?php

namespace Config;

use App\Contracts\AuthMailerInterface;
use App\Services\AuthMailer;
use App\Services\MagicLinkService;
use App\Services\UserAccountService;
use App\Services\UserRoleService;
use CodeIgniter\Config\BaseService;
use CodeIgniter\Settings\Config\Settings as SettingsConfig;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Authentication\JWTManager;
use CodeIgniter\Shield\Authentication\Passwords;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function authMailer(bool $getShared = true): AuthMailerInterface
    {
        if ($getShared) {
            return static::getSharedInstance('authMailer');
        }

        return new AuthMailer();
    }

    public static function magicLinks(bool $getShared = true): MagicLinkService
    {
        if ($getShared) {
            return static::getSharedInstance('magicLinks');
        }

        return new MagicLinkService(static::authMailer());
    }

    public static function userRoles(bool $getShared = true): UserRoleService
    {
        if ($getShared) {
            return static::getSharedInstance('userRoles');
        }

        return new UserRoleService();
    }

    public static function userAccounts(bool $getShared = true): UserAccountService
    {
        if ($getShared) {
            return static::getSharedInstance('userAccounts');
        }

        return new UserAccountService(static::userRoles(), static::magicLinks());
    }

    public static function auth(bool $getShared = true): Auth
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return \CodeIgniter\Shield\Config\Services::auth(false);
    }

    public static function passwords(bool $getShared = true): Passwords
    {
        if ($getShared) {
            return static::getSharedInstance('passwords');
        }

        return \CodeIgniter\Shield\Config\Services::passwords(false);
    }

    public static function jwtmanager(bool $getShared = true): JWTManager
    {
        if ($getShared) {
            return static::getSharedInstance('jwtmanager');
        }

        return \CodeIgniter\Shield\Config\Services::jwtmanager(false);
    }

    public static function settings(?SettingsConfig $config = null, bool $getShared = true): Settings
    {
        if ($getShared) {
            return static::getSharedInstance('settings', $config);
        }

        return \CodeIgniter\Settings\Config\Services::settings($config, false);
    }
}
