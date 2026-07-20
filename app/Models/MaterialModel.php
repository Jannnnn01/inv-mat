<?php

declare(strict_types=1);

namespace App\Models;

final class MaterialModel extends BaseCatalogModel
{
    protected $table = 'materials';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'code', 'name', 'category_id', 'unit_id', 'description',
        'allows_fraction', 'minimum_stock', 'active', 'created_by', 'updated_by',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function findWithCatalogs(int $id): ?array
    {
        return $this->select('materials.*, categories.name AS category_name, measurement_units.name AS unit_name, measurement_units.symbol AS unit_symbol')
            ->join('categories', 'categories.id = materials.category_id')
            ->join('measurement_units', 'measurement_units.id = materials.unit_id')
            ->find($id);
    }
}
