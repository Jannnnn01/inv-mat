<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class InventoryRequestItemModel extends Model
{
    protected $table = 'inventory_request_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $useTimestamps = false;
    protected $allowedFields = [
        'request_id', 'material_id', 'quantity_delta', 'unit_cost', 'no_cost_reason', 'created_at',
    ];
}
