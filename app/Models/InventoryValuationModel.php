<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class InventoryValuationModel extends Model
{
    protected $table = 'inventory_valuation_events';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $useTimestamps = false;
    protected $allowedFields = [];
}
