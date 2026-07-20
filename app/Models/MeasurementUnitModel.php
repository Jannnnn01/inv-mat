<?php

declare(strict_types=1);

namespace App\Models;

final class MeasurementUnitModel extends BaseCatalogModel
{
    protected $table = 'measurement_units';
    protected $primaryKey = 'id';
    protected $allowedFields = ['code', 'name', 'symbol', 'active', 'created_by', 'updated_by'];
}
