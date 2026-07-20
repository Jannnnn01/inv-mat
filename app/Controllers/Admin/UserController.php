<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\UserRoleService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\Services;
use DomainException;
use Throwable;

final class UserController extends BaseController
{
    public function index(): string
    {
        $model = model(UserModel::class);
        $users = $model->withIdentities()->withGroups()->orderBy('id', 'DESC')->paginate(20);

        return view('admin/users/index', [
            'users'      => $users,
            'pager'      => $model->pager,
            'roles'      => config('AuthGroups')->groups,
            'currentId'  => (int) auth()->id(),
        ]);
    }

    public function new(): string
    {
        return view('admin/users/new', [
            'roles' => config('AuthGroups')->groups,
        ]);
    }

    public function create(): RedirectResponse
    {
        $tables = config('Auth')->tables;
        $rules = [
            'username' => [
                'label' => 'Nombre de usuario',
                'rules' => "required|min_length[3]|max_length[30]|regex_match[/\\A[a-zA-Z0-9.]+\\z/]|is_unique[{$tables['users']}.username]",
            ],
            'email' => [
                'label' => 'Correo electrónico',
                'rules' => "required|max_length[254]|valid_email|is_unique[{$tables['identities']}.secret]",
            ],
            'role' => [
                'label' => 'Rol',
                'rules' => 'required|in_list[' . implode(',', UserRoleService::ROLES) . ']',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $result = Services::userAccounts()->createInvitedUser(
                (string) $this->request->getPost('username'),
                (string) $this->request->getPost('email'),
                (string) $this->request->getPost('role'),
            );
        } catch (Throwable $exception) {
            log_message('error', 'Fallo la creación administrativa de un usuario: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()->back()->withInput()->with('error', 'No fue posible crear la cuenta. Inténtalo nuevamente.');
        }

        $message = $result['emailSent']
            ? 'La cuenta fue creada y la invitación fue enviada.'
            : 'La cuenta fue creada, pero el correo no pudo enviarse. Revisa SMTP y reenvía la invitación.';

        return redirect()->route('admin-users')->with($result['emailSent'] ? 'message' : 'warning', $message);
    }

    public function toggleStatus(int $id): RedirectResponse
    {
        $user = $this->findUser($id);
        if (! $user instanceof User) {
            return redirect()->route('admin-users')->with('error', 'El usuario no existe.');
        }

        try {
            Services::userAccounts()->setActive($user, ! (bool) $user->active, (int) auth()->id());
        } catch (DomainException $exception) {
            return redirect()->route('admin-users')->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Fallo el cambio de estado del usuario {userId}: {message}', [
                'userId'  => $id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('admin-users')->with('error', 'No fue posible cambiar el estado de la cuenta.');
        }

        return redirect()->route('admin-users')->with('message', 'El estado de la cuenta fue actualizado.');
    }

    public function updateRole(int $id): RedirectResponse
    {
        $role = (string) $this->request->getPost('role');
        if (! in_array($role, UserRoleService::ROLES, true)) {
            return redirect()->route('admin-users')->with('error', 'El rol seleccionado no es válido.');
        }

        $user = $this->findUser($id);
        if (! $user instanceof User) {
            return redirect()->route('admin-users')->with('error', 'El usuario no existe.');
        }

        try {
            Services::userAccounts()->changeRole($user, $role, (int) auth()->id());
        } catch (DomainException $exception) {
            return redirect()->route('admin-users')->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Fallo el cambio de rol del usuario {userId}: {message}', [
                'userId'  => $id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('admin-users')->with('error', 'No fue posible cambiar el rol.');
        }

        return redirect()->route('admin-users')->with('message', 'El rol principal fue actualizado.');
    }

    public function resendInvitation(int $id): RedirectResponse
    {
        $user = $this->findUser($id);
        if (! $user instanceof User) {
            return redirect()->route('admin-users')->with('error', 'El usuario no existe.');
        }

        try {
            $sent = Services::userAccounts()->sendPasswordSetup($user, true);
        } catch (DomainException $exception) {
            return redirect()->route('admin-users')->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Fallo el reenvío de invitación al usuario {userId}: {message}', [
                'userId'  => $id,
                'message' => $exception->getMessage(),
            ]);
            $sent = false;
        }

        return redirect()->route('admin-users')->with(
            $sent ? 'message' : 'error',
            $sent ? 'La invitación fue reenviada.' : 'No fue posible enviar la invitación. Revisa la configuración SMTP.',
        );
    }

    private function findUser(int $id): ?User
    {
        $user = model(UserModel::class)->withIdentities()->withGroups()->findById($id);

        return $user instanceof User ? $user : null;
    }
}
