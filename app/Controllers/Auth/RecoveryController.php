<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Controllers\MagicLinkController;
use Config\Services;

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
            Services::magicLinks()->send($user);
        }

        return $this->displayMessage();
    }

    public function verify(): RedirectResponse
    {
        $response = parent::verify();

        if (auth()->loggedIn()) {
            session()->setTempdata('passwordResetAuthorized', true, 15 * MINUTE);
        }

        return $response;
    }
}
