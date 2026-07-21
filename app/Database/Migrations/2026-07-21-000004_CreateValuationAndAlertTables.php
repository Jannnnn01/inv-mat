<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateValuationAndAlertTables extends Migration
{
    public function up(): void
    {
        $this->createValuationEvents();
        $this->createAlertNotificationStates();
    }

    public function down(): void
    {
        $this->forge->dropTable('stock_alert_notification_states', true);
        $this->forge->dropTable('inventory_valuation_events', true);
    }

    private function createValuationEvents(): void
    {
        $this->forge->addField([
            'id'                          => ['type' => 'BIGINT', 'auto_increment' => true],
            'valuation_number'            => ['type' => 'VARCHAR', 'constraint' => 40],
            'event_type'                  => ['type' => 'VARCHAR', 'constraint' => 20],
            'movement_item_id'            => ['type' => 'BIGINT'],
            'original_valuation_id'       => ['type' => 'BIGINT', 'null' => true],
            'material_id'                 => ['type' => 'BIGINT'],
            'warehouse_id'                => ['type' => 'BIGINT'],
            'quantity_basis'              => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'inventory_quantity_basis'    => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'consumed_quantity_basis'     => ['type' => 'NUMERIC', 'constraint' => '14,3'],
            'previous_unit_cost'          => ['type' => 'NUMERIC', 'constraint' => '18,6', 'null' => true],
            'unit_cost'                   => ['type' => 'NUMERIC', 'constraint' => '18,6'],
            'inventory_value_delta'       => ['type' => 'NUMERIC', 'constraint' => '24,6'],
            'consumed_value_delta'        => ['type' => 'NUMERIC', 'constraint' => '24,6'],
            'reason'                      => ['type' => 'TEXT'],
            'created_by'                  => ['type' => 'INT'],
            'created_at'                  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('valuation_number');
        $this->forge->addKey(['movement_item_id', 'created_at']);
        $this->forge->addKey(['material_id', 'warehouse_id']);
        $this->forge->addForeignKey('movement_item_id', 'inventory_movement_items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('original_valuation_id', 'inventory_valuation_events', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('material_id', 'materials', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('inventory_valuation_events');

        $this->db->query("ALTER TABLE inventory_valuation_events ADD CONSTRAINT chk_valuation_event_type CHECK (event_type IN ('ALLOCATION', 'CORRECTION'))");
        $this->db->query('ALTER TABLE inventory_valuation_events ADD CONSTRAINT chk_valuation_quantities CHECK (quantity_basis > 0 AND inventory_quantity_basis >= 0 AND consumed_quantity_basis >= 0 AND inventory_quantity_basis + consumed_quantity_basis = quantity_basis)');
        $this->db->query('ALTER TABLE inventory_valuation_events ADD CONSTRAINT chk_valuation_costs CHECK (unit_cost >= 0 AND (previous_unit_cost IS NULL OR previous_unit_cost >= 0))');
        $this->db->query("ALTER TABLE inventory_valuation_events ADD CONSTRAINT chk_valuation_origin CHECK ((event_type = 'ALLOCATION' AND original_valuation_id IS NULL AND previous_unit_cost IS NULL AND inventory_value_delta >= 0 AND consumed_value_delta >= 0) OR (event_type = 'CORRECTION' AND original_valuation_id IS NOT NULL AND previous_unit_cost IS NOT NULL))");
        $this->db->query('CREATE TRIGGER trg_inventory_valuation_events_immutable BEFORE UPDATE OR DELETE ON inventory_valuation_events FOR EACH ROW EXECUTE FUNCTION prevent_inventory_history_change()');
    }

    private function createAlertNotificationStates(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'BIGINT', 'auto_increment' => true],
            'material_id'      => ['type' => 'BIGINT'],
            'warehouse_id'     => ['type' => 'BIGINT'],
            'last_notified_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['material_id', 'warehouse_id']);
        $this->forge->addForeignKey('material_id', 'materials', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('stock_alert_notification_states');
    }
}
