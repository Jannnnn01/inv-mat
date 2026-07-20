<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCatalogTables extends Migration
{
    public function up(): void
    {
        $this->createWarehouses();
        $this->createUnits();
        $this->createCategories();
        $this->createSuppliers();
        $this->createMaterials();
        $this->createStocks();
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_stocks', true);
        $this->forge->dropTable('materials', true);
        $this->forge->dropTable('suppliers', true);
        $this->forge->dropTable('categories', true);
        $this->forge->dropTable('measurement_units', true);
        $this->forge->dropTable('warehouses', true);
    }

    private function createWarehouses(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'auto_increment' => true],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_main'     => ['type' => 'BOOLEAN', 'default' => false],
            'active'      => ['type' => 'BOOLEAN', 'default' => true],
            'created_by'  => ['type' => 'INT', 'null' => true],
            'updated_by'  => ['type' => 'INT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('warehouses');

        $this->db->query('CREATE UNIQUE INDEX uq_warehouses_single_main ON warehouses (is_main) WHERE is_main = TRUE');
    }

    private function createUnits(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'auto_increment' => true],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
            'symbol'     => ['type' => 'VARCHAR', 'constraint' => 20],
            'active'     => ['type' => 'BOOLEAN', 'default' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('measurement_units');
    }

    private function createCategories(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'auto_increment' => true],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'TEXT', 'null' => true],
            'active'      => ['type' => 'BOOLEAN', 'default' => true],
            'created_by'  => ['type' => 'INT', 'null' => true],
            'updated_by'  => ['type' => 'INT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('categories');
    }

    private function createSuppliers(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGINT', 'auto_increment' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 160],
            'document_type'   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'document_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'contact_name'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'email'           => ['type' => 'VARCHAR', 'constraint' => 254, 'null' => true],
            'phone'           => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'address'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'active'          => ['type' => 'BOOLEAN', 'default' => true],
            'created_by'      => ['type' => 'INT', 'null' => true],
            'updated_by'      => ['type' => 'INT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['document_type', 'document_number']);
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('suppliers');
    }

    private function createMaterials(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGINT', 'auto_increment' => true],
            'code'            => ['type' => 'VARCHAR', 'constraint' => 40],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 160],
            'category_id'     => ['type' => 'BIGINT'],
            'unit_id'         => ['type' => 'BIGINT'],
            'description'     => ['type' => 'TEXT', 'null' => true],
            'allows_fraction' => ['type' => 'BOOLEAN', 'default' => false],
            'minimum_stock'   => ['type' => 'NUMERIC', 'constraint' => '14,3', 'default' => 0],
            'active'          => ['type' => 'BOOLEAN', 'default' => true],
            'created_by'      => ['type' => 'INT', 'null' => true],
            'updated_by'      => ['type' => 'INT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('category_id');
        $this->forge->addKey('unit_id');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('unit_id', 'measurement_units', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('materials');

        $this->db->query('ALTER TABLE materials ADD CONSTRAINT chk_materials_minimum_stock_nonnegative CHECK (minimum_stock >= 0)');
        $this->db->query("ALTER TABLE materials ADD CONSTRAINT chk_materials_integer_minimum CHECK (allows_fraction OR minimum_stock = TRUNC(minimum_stock))");
    }

    private function createStocks(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'BIGINT', 'auto_increment' => true],
            'material_id'       => ['type' => 'BIGINT'],
            'warehouse_id'      => ['type' => 'BIGINT'],
            'quantity'          => ['type' => 'NUMERIC', 'constraint' => '14,3', 'default' => 0],
            'valued_quantity'   => ['type' => 'NUMERIC', 'constraint' => '14,3', 'default' => 0],
            'average_unit_cost' => ['type' => 'NUMERIC', 'constraint' => '18,6', 'null' => true],
            'version'           => ['type' => 'INT', 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['material_id', 'warehouse_id']);
        $this->forge->addForeignKey('material_id', 'materials', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('warehouse_id', 'warehouses', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('inventory_stocks');

        $this->db->query('ALTER TABLE inventory_stocks ADD CONSTRAINT chk_inventory_stock_nonnegative CHECK (quantity >= 0)');
        $this->db->query('ALTER TABLE inventory_stocks ADD CONSTRAINT chk_inventory_valued_quantity CHECK (valued_quantity >= 0 AND valued_quantity <= quantity)');
    }
}
