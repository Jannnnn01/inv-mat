<?php

declare(strict_types=1);

namespace App\Models;

final class InventoryRequestModel extends BaseCatalogModel
{
    protected $table = 'inventory_requests';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'request_number', 'type', 'status', 'warehouse_id', 'original_movement_id',
        'reason', 'decision_comment', 'requested_by', 'requested_by_name', 'requested_at',
        'approved_by', 'approved_by_name', 'approved_at', 'executed_movement_id',
    ];
}
