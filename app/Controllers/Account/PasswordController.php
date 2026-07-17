<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Models\UserModel;

final class PasswordController extends BaseController
{
    public function edit(): string
    {
        return view('account/password', [
            'recoveryAuthorized' => session()->get('passwordResetAuthorized') === true,
        ]);
    }

    public function update(): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $recoveryAuthorized = session()->get('passwordResetAuthorized') === true;

        $rules = [
            'password' => [
                'label' => 'Nueva contrasena',
                'rules' => 'required|max_byte[72]|strong_password[]',
            ],
            'password_confirm' => [
                'label' => 'Confirmacion de contrasena',
                'rules' => 'required|matches[password]',
            ],
        ];

        if (! $recoveryAuthorized) {
            $rules['current_password'] = [
                'label' => 'Contrasena actual',
                'rules' => 'required|max_byte[72]',
            ];
        }

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if (! $recoveryAuthorized) {
            $check = auth()->check([
                'email'    => $user->email,
                'password' => (string) $this->request->getPost('current_password'),
            ]);

            if (! $check->isOK()) {
                return redirect()->back()->with('error', 'La contrasena actual no es valida.');
            }
        }

        $user->password = (string) $this->request->getPost('password');
        model(UserModel::class)->save($user);
        $user->undoForcePasswordReset();

        session()->remove('passwordResetAuthorized');
        session()->regenerate(true);

        return redirect()->route('dashboard')->with('message', 'La contrasena fue actualizada.');
    }
}
