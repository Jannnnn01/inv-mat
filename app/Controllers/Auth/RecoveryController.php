<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Controllers\MagicLinkController;
use CodeIgniter\Shield\Models\UserIdentityModel;

final class RecoveryController extends MagicLinkController
{
    public function loginAction()
    {
        $rules = [
            'email' => [
                'label' => 'Auth.email',
                'rules' => ['required', 'max_length[254]', 'valid_email'],
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->route('magic-link')->with('errors', $this->validator->getErrors());
        }

        $user = $this->provider->findByCredentials([
            'email' => (string) $this->request->getPost('email'),
        ]);

        if ($user !== null && $user->active) {
            $this->sendRecoveryLink($user);
        }

        return $this->displayMessage();
    }

    public function verify(): RedirectResponse
    {
        $response = parent::verify();

        if (auth()->loggedIn()) {
            session()->set('passwordResetAuthorized', true);
        }

        return $response;
    }

    private function sendRecoveryLink(object $user): void
    {
        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_MAGIC_LINK);

        helper('text');
        $token = random_string('crypto', 32);

        $identityModel->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => $token,
            'expires' => Time::now()->addSeconds(setting('Auth.magicLinkLifetime')),
        ]);

        /** @var IncomingRequest $request */
        $request = service('request');

        helper('email');
        $email = emailer(['mailType' => 'html'])
            ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '')
            ->setTo($user->email)
            ->setSubject(lang('Auth.magicLinkSubject'))
            ->setMessage(view(setting('Auth.views')['magic-link-email'], [
                'token'      => $token,
                'user'       => $user,
                'ipAddress'  => $request->getIPAddress(),
                'userAgent'  => (string) $request->getUserAgent(),
                'date'       => Time::now()->toDateTimeString(),
            ]));

        if (! $email->send(false)) {
            log_message('error', 'No se pudo enviar el correo de recuperacion.');
        }

        $email->clear();
        Events::trigger('passwordRecoveryRequested', $user->id);
    }
}
