<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDispatchWorkflowTables extends Migration
{
    public function up(): void
    {
        $this->createRecipients();
        $this->createSequences();
        $this->createRequests();
        $this->createRequestItems();
        $this->extendDispatchNotes();
        $this->createReservations();
        $this->backfillPendingDispatchReservations();
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_reservations', true);
        $this->db->query('ALTER TABLE inventory_dispatch_notes DROP CONSTRAINT IF EXISTS fk_dispatch_request');
        $this->db->query('ALTER TABLE inventory_dispatch_notes DROP CONSTRAINT IF EXISTS fk_dispatch_recipient');
        $this->db->query('ALTER TABLE dispatch_requests DROP CONSTRAINT IF EXISTS fk_request_dispatch_note');
        $this->db->query('DROP INDEX IF EXISTS uq_dispatch_guide_number');
        $this->forge->dropColumn('inventory_dispatch_notes', ['dispatch_request_id', 'recipient_id']);
        $this->forge->dropTable('dispatch_request_items', true);
        $this->forge->dropTable('dispatch_requests', true);
        $this->forge->dropTable('document_sequences', true);
        $this->forge->dropTable('recipients', true);
    }

    private function createRecipients(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGINT', 'auto_increment' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 180],
            'document_type'   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'document_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'address'         => ['type' => 'VARCHAR', 'constraint' => 300],
            'route'           => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'contact_name'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'email'           => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'phone'           => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'active'          => ['type' => 'BOOLEAN', 'default' => true],
            'created_by'      => ['type' => 'INT'],
            'updated_by'      => ['type' => 'INT'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['document_type', 'document_number']);
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('recipients');
    }

    private function createSequences(): void
    {
        $this->forge->addField([
            'series_key'         => ['type' => 'VARCHAR', 'constraint' => 40],
            'establishment_code' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => '001'],
            'emission_point_code'=> ['type' => 'VARCHAR', 'constraint' => 3, 'default' => '001'],
            'next_number'        => ['type' => 'BIGINT', 'default' => 1],
            'updated_at'         => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('series_key');
        $this->forge->createTable('document_sequences');
        $this->db->query("ALTER TABLE document_sequences ADD CONSTRAINT chk_document_sequence_positive CHECK (next_number > 0)");
    }

    private function createRequests(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'BIGINT', 'auto_increment' => true],
            'request_number'    => ['type' => 'VARCHAR', 'constraint' => 40],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PENDING'],
            'warehouse_id'      => ['type' => 'BIGINT'],
            'recipient_id'      => ['type' => 'BIGINT'],
            'reason'            => ['type' => 'TEXT'],
            'observations'      => ['type' => 'TEXT', 'null' => true],
            'requested_by'      => ['type' => 'INT'],
            'requested_by_name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'requested_at'      => ['type' => 'DATETIME'],
            'decided_by'        => ['type' => 'INT', 'null' => true],
            'decided_by_name'   => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'decided_at'        => ['type' => 'DATETIME', 'null' => true],
            'decision_comment'  => ['type' => 'TEXT', 'null' => true],
            'dispatch_note_id'  => ['type' => 'BIGINT', 'null' => true],
            'created_at'        => ['type' => 'DATETIME'],
            'updated_at'        => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('request_number');
        $this->forge->addKey(['status', 'requested_at']);
        $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('recipient_id', 'recipients', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('requested_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('decided_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('dispatch_requests');
        $this->db->query("ALTER TABLE dispatch_requests ADD CONSTRAINT chk_dispatch_request_status CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED', 'CONVERTED'))");
        $this->db->query('ALTER TABLE dispatch_requests ADD CONSTRAINT chk_dispatch_request_approver CHECK (decided_by IS NULL OR decided_by <> requested_by)');
    }

    private function createRequestItems(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'auto_increment' => true],
            'request_id'   => ['type' => 'BIGINT'],
            'material_id'  => ['type' => 'BIGINT'],
            'quantity'     => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'created_at'   => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['request_id', 'material_id']);
        $this->forge->addForeignKey('request_id', 'dispatch_requests', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('material_id', 'materials', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('dispatch_request_items');
        $this->db->query('ALTER TABLE dispatch_request_items ADD CONSTRAINT chk_dispatch_request_quantity CHECK (quantity > 0)');
    }

    private function extendDispatchNotes(): void
    {
        $this->forge->addColumn('inventory_dispatch_notes', [
            'dispatch_request_id' => ['type' => 'BIGINT', 'null' => true, 'after' => 'source_dispatch_note_id'],
            'recipient_id'        => ['type' => 'BIGINT', 'null' => true, 'after' => 'dispatch_request_id'],
        ]);
        $this->db->query('ALTER TABLE inventory_dispatch_notes ADD CONSTRAINT fk_dispatch_request FOREIGN KEY (dispatch_request_id) REFERENCES dispatch_requests(id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        $this->db->query('ALTER TABLE inventory_dispatch_notes ADD CONSTRAINT fk_dispatch_recipient FOREIGN KEY (recipient_id) REFERENCES recipients(id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        $this->db->query('ALTER TABLE dispatch_requests ADD CONSTRAINT fk_request_dispatch_note FOREIGN KEY (dispatch_note_id) REFERENCES inventory_dispatch_notes(id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        $this->db->query('CREATE UNIQUE INDEX uq_dispatch_guide_number ON inventory_dispatch_notes (guide_number) WHERE guide_number IS NOT NULL');
    }

    private function createReservations(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'BIGINT', 'auto_increment' => true],
            'warehouse_id'        => ['type' => 'BIGINT'],
            'material_id'         => ['type' => 'BIGINT'],
            'request_item_id'     => ['type' => 'BIGINT', 'null' => true],
            'dispatch_item_id'    => ['type' => 'BIGINT', 'null' => true],
            'initial_quantity'    => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'remaining_quantity'  => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'status'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
            'created_by'          => ['type' => 'INT'],
            'created_at'          => ['type' => 'DATETIME'],
            'updated_at'          => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['warehouse_id', 'material_id', 'status']);
        $this->forge->addUniqueKey('request_item_id');
        $this->forge->addUniqueKey('dispatch_item_id');
        $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('material_id', 'materials', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('request_item_id', 'dispatch_request_items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('dispatch_item_id', 'inventory_dispatch_items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('inventory_reservations');
        $this->db->query("ALTER TABLE inventory_reservations ADD CONSTRAINT chk_reservation_owner CHECK ((request_item_id IS NOT NULL)::int + (dispatch_item_id IS NOT NULL)::int = 1)");
        $this->db->query("ALTER TABLE inventory_reservations ADD CONSTRAINT chk_reservation_status CHECK (status IN ('ACTIVE', 'CONSUMED', 'RELEASED'))");
        $this->db->query('ALTER TABLE inventory_reservations ADD CONSTRAINT chk_reservation_quantities CHECK (initial_quantity > 0 AND remaining_quantity >= 0 AND remaining_quantity <= initial_quantity)');
    }

    private function backfillPendingDispatchReservations(): void
    {
        $this->db->query(<<<'SQL'
INSERT INTO inventory_reservations
    (warehouse_id, material_id, request_item_id, dispatch_item_id, initial_quantity, remaining_quantity, status, created_by, created_at, updated_at)
SELECT mv.warehouse_id, di.material_id, NULL, di.id,
       di.requested_quantity - di.delivered_quantity - COALESCE((SELECT SUM(c.delivered_quantity) FROM inventory_dispatch_items c JOIN inventory_dispatch_notes cn ON cn.id = c.dispatch_note_id WHERE c.source_dispatch_item_id = di.id AND NOT EXISTS (SELECT 1 FROM inventory_movements cr WHERE cr.original_movement_id = cn.movement_id)), 0),
       di.requested_quantity - di.delivered_quantity - COALESCE((SELECT SUM(c.delivered_quantity) FROM inventory_dispatch_items c JOIN inventory_dispatch_notes cn ON cn.id = c.dispatch_note_id WHERE c.source_dispatch_item_id = di.id AND NOT EXISTS (SELECT 1 FROM inventory_movements cr WHERE cr.original_movement_id = cn.movement_id)), 0),
       'ACTIVE', mv.created_by, NOW(), NOW()
  FROM inventory_dispatch_items di
  JOIN inventory_dispatch_notes dn ON dn.id = di.dispatch_note_id
  JOIN inventory_movements mv ON mv.id = dn.movement_id
 WHERE di.source_dispatch_item_id IS NULL
   AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = mv.id)
   AND di.requested_quantity - di.delivered_quantity - COALESCE((SELECT SUM(c.delivered_quantity) FROM inventory_dispatch_items c JOIN inventory_dispatch_notes cn ON cn.id = c.dispatch_note_id WHERE c.source_dispatch_item_id = di.id AND NOT EXISTS (SELECT 1 FROM inventory_movements cr WHERE cr.original_movement_id = cn.movement_id)), 0) > 0
SQL);
    }
}
