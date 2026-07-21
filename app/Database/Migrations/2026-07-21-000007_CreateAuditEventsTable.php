<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateAuditEventsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'BIGINT', 'auto_increment' => true],
            'actor_user_id'     => ['type' => 'INT', 'null' => true],
            'actor_name'        => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'action'            => ['type' => 'VARCHAR', 'constraint' => 120],
            'module'            => ['type' => 'VARCHAR', 'constraint' => 80],
            'route_name'        => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'http_method'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'request_path'      => ['type' => 'VARCHAR', 'constraint' => 500],
            'entity_type'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'entity_id'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'result'            => ['type' => 'VARCHAR', 'constraint' => 20],
            'ip_address'        => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'old_values'        => ['type' => 'JSONB', 'null' => true],
            'new_values'        => ['type' => 'JSONB', 'null' => true],
            'occurred_at'       => ['type' => 'DATETIME'],
            'retention_until'   => ['type' => 'DATE'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['occurred_at', 'id']);
        $this->forge->addKey(['actor_user_id', 'occurred_at']);
        $this->forge->addKey(['module', 'action', 'occurred_at']);
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'SET NULL');
        $this->forge->createTable('audit_events');

        $this->db->query("ALTER TABLE audit_events ADD CONSTRAINT chk_audit_result CHECK (result IN ('SUCCESS', 'FAILURE', 'DENIED'))");
        $this->db->query("ALTER TABLE audit_events ADD CONSTRAINT chk_audit_method CHECK (http_method IN ('GET', 'POST', 'PUT', 'PATCH', 'DELETE'))");
        $this->db->query("ALTER TABLE audit_events ADD CONSTRAINT chk_audit_retention CHECK (retention_until >= (occurred_at::date + INTERVAL '2 years')::date)");
        $this->db->query(<<<'SQL'
CREATE FUNCTION prevent_audit_event_change() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Los registros de auditoría son inmutables';
END;
$$ LANGUAGE plpgsql
SQL);
        $this->db->query('CREATE TRIGGER trg_audit_events_immutable BEFORE UPDATE OR DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION prevent_audit_event_change()');
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_events', true);
        $this->db->query('DROP FUNCTION IF EXISTS prevent_audit_event_change()');
    }
}
