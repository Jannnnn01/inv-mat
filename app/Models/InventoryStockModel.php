<?php

declare(strict_types=1);

namespace App\Models;

final class InventoryStockModel extends BaseCatalogModel
{
    protected $table = 'inventory_stocks';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'material_id', 'warehouse_id', 'quantity', 'valued_quantity',
        'average_unit_cost', 'version',
    ];
}
