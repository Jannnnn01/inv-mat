<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuthMailerInterface;
use CodeIgniter\Shield\Entities\User;

final class AuthMailer implements AuthMailerInterface
{
    public function sendAccessLink(User $user, string $token, bool $invitation): bool
    {
        $emailAddress = $user->email;
        if ($emailAddress === null || $emailAddress === '') {
            return false;
        }

        helper('email');

        $email = emailer(['mailType' => 'html'])
            ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '')
            ->setTo($emailAddress)
            ->setSubject($invitation ? 'Activa tu cuenta de inventario' : 'Restablece tu acceso al inventario')
            ->setMessage(view('emails/auth_access_link', [
                'user'       => $user,
                'accessUrl'  => url_to('verify-magic-link') . '?token=' . rawurlencode($token),
                'invitation' => $invitation,
                'expiresIn'  => (int) (setting('Auth.magicLinkLifetime') / MINUTE),
            ]));

        $sent = $email->send(false);
        $email->clear();

        return $sent;
    }
}
