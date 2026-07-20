<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddSinglePrimaryRoleConstraint extends Migration
{
    public function up(): void
    {
        $this->db->query(
            'CREATE UNIQUE INDEX uq_auth_groups_users_single_role ON auth_groups_users (user_id)',
        );
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX IF EXISTS uq_auth_groups_users_single_role');
    }
}
