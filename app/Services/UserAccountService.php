<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use DomainException;
use RuntimeException;

final class UserAccountService
{
    public function __construct(
        private readonly UserRoleService $roles,
        private readonly MagicLinkService $magicLinks,
    ) {
    }

    /**
     * @return array{user: User, emailSent: bool}
     */
    public function createInvitedUser(string $username, string $email, string $role): array
    {
        $database = db_connect();
        $database->transException(true)->transStart();

        $model = model(UserModel::class);
        $user = new User([
            'username' => $username,
            'email'    => strtolower($email),
            'password' => bin2hex(random_bytes(32)) . 'Aa1!',
            'active'   => 1,
        ]);

        $model->save($user);
        $created = $model->findById($model->getInsertID());
        if (! $created instanceof User) {
            throw new RuntimeException('No fue posible recuperar el usuario creado.');
        }

        $this->roles->assignPrimaryRole($created, $role);
        $created->forcePasswordReset();

        $database->transComplete();

        return [
            'user'      => $created,
            'emailSent' => $this->magicLinks->send($created, true),
        ];
    }

    public function sendPasswordSetup(User $user, bool $invitation = false): bool
    {
        if (! $user->active) {
            throw new DomainException('No se puede enviar un enlace a una cuenta inactiva.');
        }

        $user->forcePasswordReset();

        return $this->magicLinks->send($user, $invitation);
    }

    public function changeRole(User $user, string $role, int $actorId): void
    {
        $currentRole = $this->roles->primaryRole($user);
        if ($currentRole === $role) {
            return;
        }

        if ((int) $user->id === $actorId) {
            throw new DomainException('No puedes cambiar tu propio rol.');
        }

        if ($currentRole === 'admin' && $this->countActiveAdministrators() <= 1) {
            throw new DomainException('Debe permanecer al menos un administrador activo.');
        }

        $this->roles->assignPrimaryRole($user, $role);
    }

    public function setActive(User $user, bool $active, int $actorId): void
    {
        if ((bool) $user->active === $active) {
            return;
        }

        if (! $active && (int) $user->id === $actorId) {
            throw new DomainException('No puedes desactivar tu propia cuenta.');
        }

        if (! $active && $this->roles->primaryRole($user) === 'admin' && $this->countActiveAdministrators() <= 1) {
            throw new DomainException('Debe permanecer al menos un administrador activo.');
        }

        $user->active = $active ? 1 : 0;
        model(UserModel::class)->save($user);
    }

    public function hasAdministrator(): bool
    {
        return $this->countActiveAdministrators() > 0;
    }

    private function countActiveAdministrators(): int
    {
        $auth = config('Auth');

        return db_connect()->table($auth->tables['users'] . ' users')
            ->join($auth->tables['groups_users'] . ' groups', 'groups.user_id = users.id')
            ->where('groups.group', 'admin')
            ->where('users.active', 1)
            ->where('users.deleted_at', null)
            ->countAllResults();
    }
}
