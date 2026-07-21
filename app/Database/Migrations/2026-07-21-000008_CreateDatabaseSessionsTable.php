<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDatabaseSessionsTable extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS ci_sessions (
    id VARCHAR(128) PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data BYTEA NOT NULL DEFAULT '\x'
)
SQL);
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_ci_sessions_timestamp ON ci_sessions (timestamp)');
    }

    public function down(): void
    {
        $this->forge->dropTable('ci_sessions', true);
    }
}
