<?php

declare(strict_types=1);

namespace App\Models;

final class WarehouseModel extends BaseCatalogModel
{
    protected $table = 'warehouses';
    protected $primaryKey = 'id';
    protected $allowedFields = ['code', 'name', 'description', 'is_main', 'active', 'created_by', 'updated_by'];
}
