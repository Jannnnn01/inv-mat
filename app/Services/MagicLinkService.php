<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuthMailerInterface;
use CodeIgniter\Events\Events;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;

final class MagicLinkService
{
    public function __construct(private readonly AuthMailerInterface $mailer)
    {
    }

    public function send(User $user, bool $invitation = false): bool
    {
        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_MAGIC_LINK);

        $token = bin2hex(random_bytes(32));
        $identityId = $identityModel->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => $token,
            'expires' => Time::now()->addSeconds((int) setting('Auth.magicLinkLifetime')),
        ]);

        if (! $this->mailer->sendAccessLink($user, $token, $invitation)) {
            $identityModel->delete($identityId);
            log_message('error', 'No se pudo enviar un enlace de acceso para el usuario {userId}.', [
                'userId' => $user->id,
            ]);

            return false;
        }

        Events::trigger($invitation ? 'accountInvitationSent' : 'passwordRecoveryRequested', $user->id);

        return true;
    }
}
