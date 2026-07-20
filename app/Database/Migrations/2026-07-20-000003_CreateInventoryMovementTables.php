<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateInventoryMovementTables extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('inventory_stocks', [
            'total_value' => [
                'type'       => 'NUMERIC',
                'constraint' => '24,6',
                'default'    => 0,
                'after'      => 'average_unit_cost',
            ],
        ]);
        $this->db->query('ALTER TABLE inventory_stocks ADD CONSTRAINT chk_inventory_total_value_nonnegative CHECK (total_value >= 0)');

        $this->createMovements();
        $this->createMovementItems();
        $this->createRequests();
        $this->createRequestItems();
        $this->protectMovementHistory();
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_request_items', true);
        $this->forge->dropTable('inventory_requests', true);
        $this->forge->dropTable('inventory_movement_items', true);
        $this->forge->dropTable('inventory_movements', true);
        $this->db->query('DROP FUNCTION IF EXISTS prevent_inventory_history_change()');
        $this->db->query('ALTER TABLE inventory_stocks DROP CONSTRAINT IF EXISTS chk_inventory_total_value_nonnegative');
        $this->forge->dropColumn('inventory_stocks', 'total_value');
    }

    private function createMovements(): void
    {
        $this->forge->addField([
            'id'                              => ['type' => 'BIGINT', 'auto_increment' => true],
            'movement_number'                 => ['type' => 'VARCHAR', 'constraint' => 40],
            'type'                            => ['type' => 'VARCHAR', 'constraint' => 20],
            'warehouse_id'                    => ['type' => 'BIGINT'],
            'original_movement_id'            => ['type' => 'BIGINT', 'null' => true],
            'supplier_id'                     => ['type' => 'BIGINT', 'null' => true],
            'document_number'                 => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'purchase_order_number'           => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'document_date'                   => ['type' => 'DATE', 'null' => true],
            'reason'                          => ['type' => 'TEXT', 'null' => true],
            'observations'                    => ['type' => 'TEXT', 'null' => true],
            'delivered_by_user_id'            => ['type' => 'INT', 'null' => true],
            'delivered_by_name'               => ['type' => 'VARCHAR', 'constraint' => 160],
            'delivered_by_identification'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'delivered_by_position'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'delivered_by_area_name'          => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'received_by_user_id'             => ['type' => 'INT', 'null' => true],
            'received_by_name'                => ['type' => 'VARCHAR', 'constraint' => 160],
            'received_by_identification'      => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'received_by_position'            => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'received_by_area_name'           => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'has_pending_valuation'           => ['type' => 'BOOLEAN', 'default' => false],
            'created_by'                      => ['type' => 'INT'],
            'created_at'                      => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('movement_number');
        $this->forge->addKey(['warehouse_id', 'created_at']);
        $this->forge->addKey(['type', 'created_at']);
        $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('original_movement_id', 'inventory_movements', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('delivered_by_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('received_by_user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('inventory_movements');

        $this->db->query("ALTER TABLE inventory_movements ADD CONSTRAINT chk_inventory_movement_type CHECK (type IN ('ENTRY', 'EXIT', 'ADJUSTMENT', 'REVERSAL'))");
        $this->db->query("ALTER TABLE inventory_movements ADD CONSTRAINT chk_inventory_reversal_origin CHECK ((type = 'REVERSAL' AND original_movement_id IS NOT NULL) OR (type <> 'REVERSAL' AND original_movement_id IS NULL))");
    }

    private function createMovementItems(): void
    {
        $this->forge->addField([
            'id'                       => ['type' => 'BIGINT', 'auto_increment' => true],
            'movement_id'              => ['type' => 'BIGINT'],
            'material_id'              => ['type' => 'BIGINT'],
            'direction'                => ['type' => 'SMALLINT'],
            'quantity'                 => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'valued_quantity'          => ['type' => 'NUMERIC', 'constraint' => '14,3', 'default' => 0],
            'unit_cost'                => ['type' => 'NUMERIC', 'constraint' => '18,6', 'null' => true],
            'line_value'               => ['type' => 'NUMERIC', 'constraint' => '24,6', 'default' => 0],
            'pending_valuation'        => ['type' => 'BOOLEAN', 'default' => false],
            'no_cost_reason'           => ['type' => 'TEXT', 'null' => true],
            'stock_before'             => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'stock_after'              => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'valued_stock_before'      => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'valued_stock_after'       => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'average_cost_before'      => ['type' => 'NUMERIC', 'constraint' => '18,6', 'null' => true],
            'average_cost_after'       => ['type' => 'NUMERIC', 'constraint' => '18,6', 'null' => true],
            'created_at'               => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['movement_id', 'material_id']);
        $this->forge->addKey(['material_id', 'created_at']);
        $this->forge->addForeignKey('movement_id', 'inventory_movements', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('material_id', 'materials', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('inventory_movement_items');

        $this->db->query('ALTER TABLE inventory_movement_items ADD CONSTRAINT chk_inventory_item_direction CHECK (direction IN (-1, 1))');
        $this->db->query('ALTER TABLE inventory_movement_items ADD CONSTRAINT chk_inventory_item_quantity CHECK (quantity > 0)');
        $this->db->query('ALTER TABLE inventory_movement_items ADD CONSTRAINT chk_inventory_item_valued_quantity CHECK (valued_quantity >= 0 AND valued_quantity <= quantity)');
        $this->db->query('ALTER TABLE inventory_movement_items ADD CONSTRAINT chk_inventory_item_line_value CHECK (line_value >= 0)');
        $this->db->query('ALTER TABLE inventory_movement_items ADD CONSTRAINT chk_inventory_item_stock CHECK (stock_before >= 0 AND stock_after >= 0 AND valued_stock_before >= 0 AND valued_stock_after >= 0)');
    }

    private function createRequests(): void
    {
        $this->forge->addField([
            'id'                    => ['type' => 'BIGINT', 'auto_increment' => true],
            'request_number'        => ['type' => 'VARCHAR', 'constraint' => 40],
            'type'                  => ['type' => 'VARCHAR', 'constraint' => 20],
            'status'                => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PENDING'],
            'warehouse_id'          => ['type' => 'BIGINT'],
            'original_movement_id'  => ['type' => 'BIGINT', 'null' => true],
            'reason'                => ['type' => 'TEXT'],
            'decision_comment'      => ['type' => 'TEXT', 'null' => true],
            'requested_by'          => ['type' => 'INT'],
            'requested_by_name'     => ['type' => 'VARCHAR', 'constraint' => 160],
            'requested_at'          => ['type' => 'DATETIME'],
            'approved_by'           => ['type' => 'INT', 'null' => true],
            'approved_by_name'      => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'approved_at'           => ['type' => 'DATETIME', 'null' => true],
            'executed_movement_id'  => ['type' => 'BIGINT', 'null' => true],
            'created_at'            => ['type' => 'DATETIME'],
            'updated_at'            => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('request_number');
        $this->forge->addKey(['status', 'requested_at']);
        $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('original_movement_id', 'inventory_movements', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('requested_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('approved_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('executed_movement_id', 'inventory_movements', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('inventory_requests');

        $this->db->query("ALTER TABLE inventory_requests ADD CONSTRAINT chk_inventory_request_type CHECK (type IN ('ADJUSTMENT', 'REVERSAL'))");
        $this->db->query("ALTER TABLE inventory_requests ADD CONSTRAINT chk_inventory_request_status CHECK (status IN ('PENDING', 'REJECTED', 'EXECUTED'))");
        $this->db->query("ALTER TABLE inventory_requests ADD CONSTRAINT chk_inventory_request_origin CHECK ((type = 'REVERSAL' AND original_movement_id IS NOT NULL) OR (type = 'ADJUSTMENT' AND original_movement_id IS NULL))");
        $this->db->query('ALTER TABLE inventory_requests ADD CONSTRAINT chk_inventory_request_approver CHECK (approved_by IS NULL OR approved_by <> requested_by)');
    }

    private function createRequestItems(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGINT', 'auto_increment' => true],
            'request_id'      => ['type' => 'BIGINT'],
            'material_id'     => ['type' => 'BIGINT'],
            'quantity_delta'  => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'unit_cost'       => ['type' => 'NUMERIC', 'constraint' => '18,6', 'null' => true],
            'no_cost_reason'  => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['request_id', 'material_id']);
        $this->forge->addForeignKey('request_id', 'inventory_requests', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('material_id', 'materials', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('inventory_request_items');

        $this->db->query('ALTER TABLE inventory_request_items ADD CONSTRAINT chk_inventory_request_item_delta CHECK (quantity_delta <> 0)');
        $this->db->query('ALTER TABLE inventory_request_items ADD CONSTRAINT chk_inventory_request_item_cost CHECK (unit_cost IS NULL OR unit_cost >= 0)');
    }

    private function protectMovementHistory(): void
    {
        $this->db->query(<<<'SQL'
CREATE FUNCTION prevent_inventory_history_change() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Los movimientos de inventario son inmutables';
END;
$$ LANGUAGE plpgsql
SQL);
        $this->db->query('CREATE TRIGGER trg_inventory_movements_immutable BEFORE UPDATE OR DELETE ON inventory_movements FOR EACH ROW EXECUTE FUNCTION prevent_inventory_history_change()');
        $this->db->query('CREATE TRIGGER trg_inventory_movement_items_immutable BEFORE UPDATE OR DELETE ON inventory_movement_items FOR EACH ROW EXECUTE FUNCTION prevent_inventory_history_change()');
    }
}
