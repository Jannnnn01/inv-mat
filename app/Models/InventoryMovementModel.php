<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class InventoryMovementModel extends Model
{
    protected $table = 'inventory_movements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $useTimestamps = false;
    protected $allowedFields = [];
}
