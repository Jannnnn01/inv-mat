<?php

declare(strict_types=1);

namespace App\Contracts;

use CodeIgniter\Shield\Entities\User;

interface AuthMailerInterface
{
    public function sendAccessLink(User $user, string $token, bool $invitation): bool;
}
