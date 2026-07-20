<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CategoryModel;
use App\Models\BaseCatalogModel;
use App\Models\MaterialModel;
use App\Models\MeasurementUnitModel;
use App\Models\SupplierModel;
use App\Models\WarehouseModel;
use DomainException;
use InvalidArgumentException;

final class CatalogStateService
{
    public function toggle(string $catalog, int $id, int $actorId): bool
    {
        $model = $this->modelFor($catalog);
        $record = $model->find($id);

        if ($record === null) {
            throw new DomainException('El registro solicitado no existe.');
        }

        $newState = ! (bool) $record['active'];
        if (! $newState) {
            $this->assertCanDeactivate($catalog, $record);
        }

        $model->update($id, [
            'active'     => $newState,
            'updated_by' => $actorId,
        ]);

        return $newState;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function assertCanDeactivate(string $catalog, array $record): void
    {
        if ($catalog === 'warehouses' && (bool) $record['is_main']) {
            throw new DomainException('La bodega principal no puede desactivarse.');
        }

        if ($catalog === 'categories') {
            $inUse = model(MaterialModel::class)
                ->where('category_id', $record['id'])
                ->where('active', true)
                ->countAllResults() > 0;
            if ($inUse) {
                throw new DomainException('Desactiva primero los materiales activos de esta categoría.');
            }
        }

        if ($catalog === 'measurement_units') {
            $inUse = model(MaterialModel::class)
                ->where('unit_id', $record['id'])
                ->where('active', true)
                ->countAllResults() > 0;
            if ($inUse) {
                throw new DomainException('Desactiva primero los materiales activos que usan esta unidad.');
            }
        }
    }

    private function modelFor(string $catalog): BaseCatalogModel
    {
        return match ($catalog) {
            'warehouses'       => model(WarehouseModel::class),
            'measurement_units' => model(MeasurementUnitModel::class),
            'categories'       => model(CategoryModel::class),
            'suppliers'        => model(SupplierModel::class),
            'materials'        => model(MaterialModel::class),
            default            => throw new InvalidArgumentException('Catálogo no permitido.'),
        };
    }
}
