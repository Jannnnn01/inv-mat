<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use RuntimeException;

final class UserRoleService
{
    public const ROLES = ['admin', 'warehouse', 'viewer'];

    public function assignPrimaryRole(User $user, string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException('El rol principal no es valido.');
        }

        $database = db_connect();
        $database->transException(true)->transStart();

        $user->syncGroups($role);

        if ($user->getGroups() !== [$role]) {
            throw new RuntimeException('No fue posible establecer un unico rol principal.');
        }

        $database->transComplete();
    }

    public function primaryRole(User $user): ?string
    {
        $groups = $user->getGroups();

        return $groups[0] ?? null;
    }
}
