<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\Auth as ShieldAuth;

class Auth extends ShieldAuth
{
    public array $views = [
        'login'                       => '\App\Views\auth\login',
        'register'                    => '\CodeIgniter\Shield\Views\register',
        'layout'                      => '\App\Views\auth\layout',
        'action_email_2fa'            => '\CodeIgniter\Shield\Views\email_2fa_show',
        'action_email_2fa_verify'     => '\CodeIgniter\Shield\Views\email_2fa_verify',
        'action_email_2fa_email'      => '\CodeIgniter\Shield\Views\Email\email_2fa_email',
        'action_email_activate_show'  => '\CodeIgniter\Shield\Views\email_activate_show',
        'action_email_activate_email' => '\CodeIgniter\Shield\Views\Email\email_activate_email',
        'magic-link-login'            => '\App\Views\auth\magic_link_form',
        'magic-link-message'          => '\App\Views\auth\recovery_message',
        'magic-link-email'            => '\CodeIgniter\Shield\Views\Email\magic_link_email',
    ];

    public array $redirects = [
        'register'          => '/',
        'login'             => 'dashboard',
        'logout'            => 'login',
        'force_reset'       => 'mi-cuenta/contrasena',
        'permission_denied' => 'dashboard',
        'group_denied'      => 'dashboard',
    ];

    public bool $allowRegistration = false;

    public bool $allowMagicLinkLogins = true;

    public int $magicLinkLifetime = 15 * MINUTE;

    public array $sessionConfig = [
        'field'              => 'user',
        'allowRemembering'   => false,
        'rememberCookieName' => 'invmat_remember',
        'rememberLength'     => 7 * DAY,
    ];

    public int $minimumPasswordLength = 12;

    public function loginRedirect(): string
    {
        if (session()->getTempdata('magicLogin') === true) {
            return site_url('mi-cuenta/contrasena');
        }

        return parent::loginRedirect();
    }
}
