<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Models\WarehouseModel;
use App\Services\DashboardService;

final class AlertController extends BaseController
{
    public function index(): string
    {
        $warehouseId = (int) $this->request->getGet('warehouse_id');

        return view('inventory/alerts/index', [
            'records'     => (new DashboardService())->lowStock($warehouseId > 0 ? $warehouseId : null),
            'warehouses'  => model(WarehouseModel::class)->where('active', true)->orderBy('name')->findAll(),
            'warehouseId' => $warehouseId,
        ]);
    }
}
