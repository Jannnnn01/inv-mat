<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddDispatchFulfillmentLinks extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('inventory_dispatch_notes', [
            'source_dispatch_note_id' => ['type' => 'BIGINT', 'null' => true, 'after' => 'movement_id'],
        ]);
        $this->forge->addColumn('inventory_dispatch_items', [
            'source_dispatch_item_id' => ['type' => 'BIGINT', 'null' => true, 'after' => 'material_id'],
        ]);

        $this->db->query('ALTER TABLE inventory_dispatch_notes ADD CONSTRAINT fk_dispatch_source_note FOREIGN KEY (source_dispatch_note_id) REFERENCES inventory_dispatch_notes(id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        $this->db->query('ALTER TABLE inventory_dispatch_items ADD CONSTRAINT fk_dispatch_source_item FOREIGN KEY (source_dispatch_item_id) REFERENCES inventory_dispatch_items(id) ON UPDATE RESTRICT ON DELETE RESTRICT');
        $this->db->query('CREATE INDEX idx_dispatch_notes_source ON inventory_dispatch_notes (source_dispatch_note_id)');
        $this->db->query('CREATE INDEX idx_dispatch_items_source ON inventory_dispatch_items (source_dispatch_item_id)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX IF EXISTS idx_dispatch_items_source');
        $this->db->query('DROP INDEX IF EXISTS idx_dispatch_notes_source');
        $this->db->query('ALTER TABLE inventory_dispatch_items DROP CONSTRAINT IF EXISTS fk_dispatch_source_item');
        $this->db->query('ALTER TABLE inventory_dispatch_notes DROP CONSTRAINT IF EXISTS fk_dispatch_source_note');
        $this->forge->dropColumn('inventory_dispatch_items', 'source_dispatch_item_id');
        $this->forge->dropColumn('inventory_dispatch_notes', 'source_dispatch_note_id');
    }
}
