<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDispatchAndAttachmentTables extends Migration
{
    public function up(): void
    {
        $this->createDispatchNotes();
        $this->createDispatchItems();
        $this->createAttachments();
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_attachments', true);
        $this->forge->dropTable('inventory_dispatch_items', true);
        $this->forge->dropTable('inventory_dispatch_notes', true);
        $this->db->query('DROP FUNCTION IF EXISTS prevent_inventory_attachment_delete()');
    }

    private function createDispatchNotes(): void
    {
        $this->forge->addField([
            'id'                      => ['type' => 'BIGINT', 'auto_increment' => true],
            'movement_id'             => ['type' => 'BIGINT'],
            'guide_number'            => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'authorization_number'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'issue_date'              => ['type' => 'DATE', 'null' => true],
            'start_date'              => ['type' => 'DATE', 'null' => true],
            'end_date'                => ['type' => 'DATE', 'null' => true],
            'issuer_name'             => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'issuer_tax_identifier'   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'transporter_name'        => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'transporter_identifier'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'vehicle_plate'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'origin_place'            => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'destination_name'        => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'destination_identifier'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'destination_address'     => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'route_description'       => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'related_document_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'description'             => ['type' => 'TEXT', 'null' => true],
            'created_at'              => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('movement_id');
        $this->forge->addKey(['guide_number', 'issue_date']);
        $this->forge->addForeignKey('movement_id', 'inventory_movements', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('inventory_dispatch_notes');

        $this->db->query('ALTER TABLE inventory_dispatch_notes ADD CONSTRAINT chk_dispatch_dates CHECK (start_date IS NULL OR end_date IS NULL OR end_date >= start_date)');
        $this->db->query('CREATE TRIGGER trg_inventory_dispatch_notes_immutable BEFORE UPDATE OR DELETE ON inventory_dispatch_notes FOR EACH ROW EXECUTE FUNCTION prevent_inventory_history_change()');
    }

    private function createDispatchItems(): void
    {
        $this->forge->addField([
            'id'                 => ['type' => 'BIGINT', 'auto_increment' => true],
            'dispatch_note_id'   => ['type' => 'BIGINT'],
            'material_id'        => ['type' => 'BIGINT'],
            'requested_quantity' => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'delivered_quantity' => ['type' => 'NUMERIC', 'constraint' => '14,3', 'default' => 0],
            'status'             => ['type' => 'VARCHAR', 'constraint' => 20],
            'created_at'         => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['dispatch_note_id', 'material_id']);
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addForeignKey('dispatch_note_id', 'inventory_dispatch_notes', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('material_id', 'materials', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('inventory_dispatch_items');

        $this->db->query("ALTER TABLE inventory_dispatch_items ADD CONSTRAINT chk_dispatch_item_status CHECK (status IN ('DELIVERED', 'PARTIAL', 'PENDING'))");
        $this->db->query('ALTER TABLE inventory_dispatch_items ADD CONSTRAINT chk_dispatch_item_quantities CHECK (requested_quantity > 0 AND delivered_quantity >= 0 AND delivered_quantity <= requested_quantity)');
        $this->db->query("ALTER TABLE inventory_dispatch_items ADD CONSTRAINT chk_dispatch_item_status_quantity CHECK ((status = 'PENDING' AND delivered_quantity = 0) OR (status = 'PARTIAL' AND delivered_quantity > 0 AND delivered_quantity < requested_quantity) OR (status = 'DELIVERED' AND delivered_quantity = requested_quantity))");
        $this->db->query('CREATE TRIGGER trg_inventory_dispatch_items_immutable BEFORE UPDATE OR DELETE ON inventory_dispatch_items FOR EACH ROW EXECUTE FUNCTION prevent_inventory_history_change()');
    }

    private function createAttachments(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'auto_increment' => true],
            'movement_id'   => ['type' => 'BIGINT'],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'internal_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'object_key'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'storage_driver'=> ['type' => 'VARCHAR', 'constraint' => 20],
            'mime_type'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'extension'     => ['type' => 'VARCHAR', 'constraint' => 10],
            'size_bytes'    => ['type' => 'BIGINT'],
            'sha256'        => ['type' => 'CHAR', 'constraint' => 64],
            'uploaded_by'   => ['type' => 'INT'],
            'uploaded_at'   => ['type' => 'DATETIME'],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
            'archived_by'   => ['type' => 'INT', 'null' => true],
            'archived_at'   => ['type' => 'DATETIME', 'null' => true],
            'archive_reason'=> ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('object_key');
        $this->forge->addKey(['movement_id', 'status']);
        $this->forge->addKey('sha256');
        $this->forge->addForeignKey('movement_id', 'inventory_movements', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('archived_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('inventory_attachments');

        $this->db->query("ALTER TABLE inventory_attachments ADD CONSTRAINT chk_attachment_driver CHECK (storage_driver IN ('local', 's3'))");
        $this->db->query("ALTER TABLE inventory_attachments ADD CONSTRAINT chk_attachment_type CHECK (mime_type IN ('application/pdf', 'image/jpeg', 'image/png') AND extension IN ('pdf', 'jpg', 'jpeg', 'png'))");
        $this->db->query('ALTER TABLE inventory_attachments ADD CONSTRAINT chk_attachment_size CHECK (size_bytes > 0 AND size_bytes <= 10485760)');
        $this->db->query("ALTER TABLE inventory_attachments ADD CONSTRAINT chk_attachment_status CHECK (status IN ('ACTIVE', 'ARCHIVED'))");
        $this->db->query("ALTER TABLE inventory_attachments ADD CONSTRAINT chk_attachment_archive CHECK ((status = 'ACTIVE' AND archived_by IS NULL AND archived_at IS NULL AND archive_reason IS NULL) OR (status = 'ARCHIVED' AND archived_by IS NOT NULL AND archived_at IS NOT NULL AND archive_reason IS NOT NULL))");
        $this->db->query(<<<'SQL'
CREATE FUNCTION prevent_inventory_attachment_delete() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Los archivos históricos no se eliminan físicamente';
END;
$$ LANGUAGE plpgsql
SQL);
        $this->db->query('CREATE TRIGGER trg_inventory_attachments_no_delete BEFORE DELETE ON inventory_attachments FOR EACH ROW EXECUTE FUNCTION prevent_inventory_attachment_delete()');
    }
}
