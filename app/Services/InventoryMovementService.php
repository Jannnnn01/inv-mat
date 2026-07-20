<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use Throwable;

final class InventoryMovementService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @param array<string, mixed>       $header
     * @param list<array<string, mixed>> $items
     */
    public function createEntry(array $header, array $items, int $userId): int
    {
        return $this->createOperationalMovement('ENTRY', $header, $items, $userId);
    }

    /**
     * @param array<string, mixed>       $header
     * @param list<array<string, mixed>> $items
     */
    public function createExit(array $header, array $items, int $userId): int
    {
        return $this->createOperationalMovement('EXIT', $header, $items, $userId);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public function requestAdjustment(int $warehouseId, string $reason, array $items, int $userId): int
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
            throw new DomainException('El motivo del ajuste debe tener entre 5 y 2000 caracteres.');
        }

        $this->begin();
        try {
            $this->assertActiveWarehouse($warehouseId);
            $normalized = $this->normalizeAdjustmentItems($items);
            $now = date('Y-m-d H:i:s');
            $requestId = $this->insertAndReturnId('inventory_requests', [
                'request_number'       => $this->reference('SOL'),
                'type'                 => 'ADJUSTMENT',
                'status'               => 'PENDING',
                'warehouse_id'         => $warehouseId,
                'original_movement_id' => null,
                'reason'               => $reason,
                'requested_by'         => $userId,
                'requested_by_name'    => $this->userName($userId),
                'requested_at'         => $now,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            foreach ($normalized as $item) {
                $this->db->table('inventory_request_items')->insert([
                    'request_id'     => $requestId,
                    'material_id'    => $item['material_id'],
                    'quantity_delta' => $item['quantity_delta'],
                    'unit_cost'      => $item['unit_cost'],
                    'no_cost_reason' => $item['no_cost_reason'],
                    'created_at'     => $now,
                ]);
            }

            $this->commit();

            return $requestId;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function requestReversal(int $movementId, string $reason, int $userId): int
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
            throw new DomainException('El motivo de la reversión debe tener entre 5 y 2000 caracteres.');
        }

        $this->begin();
        try {
            $movement = $this->db->table('inventory_movements')->where('id', $movementId)->get()->getRowArray();
            if ($movement === null || $movement['type'] === 'REVERSAL') {
                throw new DomainException('El movimiento seleccionado no admite reversión.');
            }

            $existing = $this->db->table('inventory_requests')
                ->where('type', 'REVERSAL')
                ->where('original_movement_id', $movementId)
                ->whereIn('status', ['PENDING', 'EXECUTED'])
                ->countAllResults();
            if ($existing > 0) {
                throw new DomainException('Este movimiento ya tiene una solicitud de reversión vigente.');
            }

            $now = date('Y-m-d H:i:s');
            $requestId = $this->insertAndReturnId('inventory_requests', [
                'request_number'       => $this->reference('SOL'),
                'type'                 => 'REVERSAL',
                'status'               => 'PENDING',
                'warehouse_id'         => (int) $movement['warehouse_id'],
                'original_movement_id' => $movementId,
                'reason'               => $reason,
                'requested_by'         => $userId,
                'requested_by_name'    => $this->userName($userId),
                'requested_at'         => $now,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            $this->commit();

            return $requestId;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function decideRequest(int $requestId, bool $approve, string $comment, int $approverId): ?int
    {
        $comment = trim($comment);
        if (mb_strlen($comment) > 1000) {
            throw new DomainException('El comentario no puede superar 1000 caracteres.');
        }
        if (! $approve && mb_strlen($comment) < 5) {
            throw new DomainException('Indica el motivo del rechazo.');
        }

        $this->begin();
        try {
            $request = $this->db->query(
                'SELECT * FROM inventory_requests WHERE id = ? FOR UPDATE',
                [$requestId],
            )->getRowArray();
            if ($request === null) {
                throw new DomainException('La solicitud no existe.');
            }
            if ($request['status'] !== 'PENDING') {
                throw new DomainException('La solicitud ya fue decidida.');
            }
            if ((int) $request['requested_by'] === $approverId) {
                throw new DomainException('No puedes aprobar ni rechazar tu propia solicitud.');
            }

            $approverName = $this->userName($approverId);
            $now = date('Y-m-d H:i:s');
            if (! $approve) {
                $this->db->table('inventory_requests')->where('id', $requestId)->update([
                    'status'            => 'REJECTED',
                    'decision_comment'  => $comment,
                    'approved_by'       => $approverId,
                    'approved_by_name'  => $approverName,
                    'approved_at'       => $now,
                    'updated_at'        => $now,
                ]);
                $this->commit();

                return null;
            }

            $header = [
                'reason'            => $request['reason'],
                'observations'      => $comment !== '' ? $comment : null,
                'delivered_by_name' => $request['requested_by_name'],
                'received_by_name'  => $approverName,
            ];

            if ($request['type'] === 'ADJUSTMENT') {
                $requestItems = $this->db->table('inventory_request_items')
                    ->where('request_id', $requestId)
                    ->orderBy('material_id')
                    ->get()->getResultArray();
                $effects = $this->prepareAdjustmentEffects((int) $request['warehouse_id'], $requestItems);
                $movementId = $this->insertMovement('ADJUSTMENT', (int) $request['warehouse_id'], null, $header, $effects, $approverId);
            } else {
                $movementId = $this->executeReversal($request, $header, $approverId);
            }

            $this->db->table('inventory_requests')->where('id', $requestId)->update([
                'status'                => 'EXECUTED',
                'decision_comment'      => $comment !== '' ? $comment : null,
                'approved_by'           => $approverId,
                'approved_by_name'      => $approverName,
                'approved_at'           => $now,
                'executed_movement_id'  => $movementId,
                'updated_at'            => $now,
            ]);
            $this->commit();

            return $movementId;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed>       $header
     * @param list<array<string, mixed>> $items
     */
    private function createOperationalMovement(string $type, array $header, array $items, int $userId): int
    {
        $warehouseId = (int) ($header['warehouse_id'] ?? 0);
        $this->assertResponsibleNames($header);

        $this->begin();
        try {
            $this->assertActiveWarehouse($warehouseId);
            if ($type === 'ENTRY' && ! empty($header['supplier_id'])) {
                $supplier = $this->db->table('suppliers')->where('id', (int) $header['supplier_id'])->where('active', true)->get()->getRowArray();
                if ($supplier === null) {
                    throw new DomainException('El proveedor seleccionado no está disponible.');
                }
            }

            $effects = $this->prepareOperationalEffects($type, $warehouseId, $items);
            $movementId = $this->insertMovement($type, $warehouseId, null, $header, $effects, $userId);
            $this->commit();

            return $movementId;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function prepareOperationalEffects(string $type, int $warehouseId, array $items): array
    {
        if ($items === []) {
            throw new DomainException('Agrega al menos un material.');
        }

        $items = $this->sortAndRejectDuplicates($items);
        $effects = [];
        foreach ($items as $item) {
            $material = $this->activeMaterial((int) ($item['material_id'] ?? 0));
            $quantity = trim((string) ($item['quantity'] ?? ''));
            if (! (new QuantityService())->isValid($quantity, (bool) $material['allows_fraction']) || (float) $quantity <= 0) {
                throw new DomainException('Una cantidad no es válida para el material ' . $material['name'] . '.');
            }

            $stock = $this->lockStock((int) $material['id'], $warehouseId);
            if ($type === 'ENTRY') {
                $cost = $this->normalizeOptionalCost($item['unit_cost'] ?? null);
                $noCostReason = trim((string) ($item['no_cost_reason'] ?? '')) ?: null;
                if ($cost === null && ($noCostReason === null || mb_strlen($noCostReason) < 5)) {
                    throw new DomainException('Indica por qué no existe costo para ' . $material['name'] . '.');
                }
                if ($noCostReason !== null && mb_strlen($noCostReason) > 500) {
                    throw new DomainException('El motivo sin costo no puede superar 500 caracteres.');
                }
                $effects[] = $this->positiveEffect($material, $stock, $quantity, $cost, $noCostReason);
            } else {
                $effects[] = $this->negativeEffect($material, $stock, $quantity);
            }
        }

        return $effects;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function normalizeAdjustmentItems(array $items): array
    {
        if ($items === []) {
            throw new DomainException('Agrega al menos un material al ajuste.');
        }

        $items = $this->sortAndRejectDuplicates($items);
        $normalized = [];
        foreach ($items as $item) {
            $material = $this->activeMaterial((int) ($item['material_id'] ?? 0));
            $delta = trim((string) ($item['quantity_delta'] ?? ''));
            $absolute = ltrim($delta, '+-');
            if (! preg_match('/\A[+-]?\d{1,11}(?:\.\d{1,3})?\z/', $delta)
                || (float) $delta === 0.0
                || ! (new QuantityService())->isValid($absolute, (bool) $material['allows_fraction'])) {
                throw new DomainException('La variación no es válida para ' . $material['name'] . '.');
            }

            $cost = null;
            $reason = null;
            if (! str_starts_with($delta, '-')) {
                $cost = $this->normalizeOptionalCost($item['unit_cost'] ?? null);
                $reason = trim((string) ($item['no_cost_reason'] ?? '')) ?: null;
                if ($cost === null && ($reason === null || mb_strlen($reason) < 5)) {
                    throw new DomainException('Indica por qué el aumento de ' . $material['name'] . ' no tiene costo.');
                }
                if ($reason !== null && mb_strlen($reason) > 500) {
                    throw new DomainException('El motivo sin costo no puede superar 500 caracteres.');
                }
            }

            $normalized[] = [
                'material_id'    => (int) $material['id'],
                'quantity_delta' => $delta,
                'unit_cost'      => $cost,
                'no_cost_reason' => $reason,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function prepareAdjustmentEffects(int $warehouseId, array $items): array
    {
        $effects = [];
        foreach ($items as $item) {
            $material = $this->activeMaterial((int) $item['material_id'], false);
            $stock = $this->lockStock((int) $material['id'], $warehouseId);
            $delta = (string) $item['quantity_delta'];
            $quantity = ltrim($delta, '+-');
            $effects[] = str_starts_with($delta, '-')
                ? $this->negativeEffect($material, $stock, $quantity)
                : $this->positiveEffect($material, $stock, $quantity, $item['unit_cost'], $item['no_cost_reason']);
        }

        return $effects;
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $header
     */
    private function executeReversal(array $request, array $header, int $approverId): int
    {
        $originalId = (int) $request['original_movement_id'];
        $alreadyReversed = $this->db->table('inventory_movements')
            ->where('type', 'REVERSAL')
            ->where('original_movement_id', $originalId)
            ->countAllResults();
        if ($alreadyReversed > 0) {
            throw new DomainException('El movimiento ya fue revertido.');
        }

        $originalItems = $this->db->table('inventory_movement_items')
            ->where('movement_id', $originalId)
            ->orderBy('material_id')
            ->get()->getResultArray();
        if ($originalItems === []) {
            throw new DomainException('El movimiento original no tiene detalles.');
        }

        $effects = [];
        foreach ($originalItems as $item) {
            $material = $this->activeMaterial((int) $item['material_id'], false);
            $stock = $this->lockStock((int) $material['id'], (int) $request['warehouse_id']);
            $effects[] = [
                'material'          => $material,
                'stock'             => $stock,
                'direction'         => -((int) $item['direction']),
                'quantity'          => (string) $item['quantity'],
                'valued_quantity'   => (string) $item['valued_quantity'],
                'unit_cost'         => $item['unit_cost'],
                'line_value'        => (string) $item['line_value'],
                'pending_valuation' => filter_var($item['pending_valuation'], FILTER_VALIDATE_BOOL),
                'no_cost_reason'     => $item['no_cost_reason'],
            ];
        }

        return $this->insertMovement(
            'REVERSAL',
            (int) $request['warehouse_id'],
            $originalId,
            $header,
            $effects,
            $approverId,
        );
    }

    /**
     * @param array<string, mixed>       $header
     * @param list<array<string, mixed>> $effects
     */
    private function insertMovement(string $type, int $warehouseId, ?int $originalId, array $header, array $effects, int $userId): int
    {
        $now = date('Y-m-d H:i:s');
        $pending = in_array(true, array_column($effects, 'pending_valuation'), true);
        $movementId = $this->insertAndReturnId('inventory_movements', [
            'movement_number'             => $this->reference('MOV'),
            'type'                        => $type,
            'warehouse_id'                => $warehouseId,
            'original_movement_id'        => $originalId,
            'supplier_id'                 => ! empty($header['supplier_id']) ? (int) $header['supplier_id'] : null,
            'document_number'             => $this->nullable($header['document_number'] ?? null),
            'purchase_order_number'       => $this->nullable($header['purchase_order_number'] ?? null),
            'document_date'               => $this->nullable($header['document_date'] ?? null),
            'reason'                      => $this->nullable($header['reason'] ?? null),
            'observations'                => $this->nullable($header['observations'] ?? null),
            'delivered_by_user_id'        => ! empty($header['delivered_by_user_id']) ? (int) $header['delivered_by_user_id'] : null,
            'delivered_by_name'           => trim((string) $header['delivered_by_name']),
            'delivered_by_identification' => $this->nullable($header['delivered_by_identification'] ?? null),
            'delivered_by_position'       => $this->nullable($header['delivered_by_position'] ?? null),
            'delivered_by_area_name'      => $this->nullable($header['delivered_by_area_name'] ?? null),
            'received_by_user_id'         => ! empty($header['received_by_user_id']) ? (int) $header['received_by_user_id'] : null,
            'received_by_name'            => trim((string) $header['received_by_name']),
            'received_by_identification'  => $this->nullable($header['received_by_identification'] ?? null),
            'received_by_position'        => $this->nullable($header['received_by_position'] ?? null),
            'received_by_area_name'       => $this->nullable($header['received_by_area_name'] ?? null),
            'has_pending_valuation'       => $pending,
            'created_by'                  => $userId,
            'created_at'                  => $now,
        ]);

        foreach ($effects as $effect) {
            $after = $this->applyStockEffect($effect, $now);
            $stock = $effect['stock'];
            $this->db->table('inventory_movement_items')->insert([
                'movement_id'         => $movementId,
                'material_id'         => (int) $effect['material']['id'],
                'direction'           => $effect['direction'],
                'quantity'            => $effect['quantity'],
                'valued_quantity'     => $effect['valued_quantity'],
                'unit_cost'           => $effect['unit_cost'],
                'line_value'          => $effect['line_value'],
                'pending_valuation'   => $effect['pending_valuation'],
                'no_cost_reason'      => $effect['no_cost_reason'],
                'stock_before'        => $stock['quantity'],
                'stock_after'         => $after['quantity'],
                'valued_stock_before' => $stock['valued_quantity'],
                'valued_stock_after'  => $after['valued_quantity'],
                'average_cost_before' => $stock['average_unit_cost'],
                'average_cost_after'  => $after['average_unit_cost'],
                'created_at'          => $now,
            ]);
        }

        return $movementId;
    }

    /** @return array<string, mixed> */
    private function applyStockEffect(array $effect, string $now): array
    {
        $result = $this->db->query(<<<'SQL'
UPDATE inventory_stocks
SET quantity = quantity + (? * ?::numeric),
    valued_quantity = valued_quantity + (? * ?::numeric),
    total_value = total_value + (? * ?::numeric),
    average_unit_cost = CASE
        WHEN valued_quantity + (? * ?::numeric) > 0
        THEN ROUND((total_value + (? * ?::numeric)) / (valued_quantity + (? * ?::numeric)), 6)
        ELSE NULL
    END,
    version = version + 1,
    updated_at = ?
WHERE id = ?
  AND quantity + (? * ?::numeric) >= 0
  AND valued_quantity + (? * ?::numeric) >= 0
  AND valued_quantity + (? * ?::numeric) <= quantity + (? * ?::numeric)
  AND total_value + (? * ?::numeric) >= 0
RETURNING quantity, valued_quantity, average_unit_cost, total_value, version
SQL, [
            $effect['direction'], $effect['quantity'],
            $effect['direction'], $effect['valued_quantity'],
            $effect['direction'], $effect['line_value'],
            $effect['direction'], $effect['valued_quantity'],
            $effect['direction'], $effect['line_value'],
            $effect['direction'], $effect['valued_quantity'],
            $now, $effect['stock']['id'],
            $effect['direction'], $effect['quantity'],
            $effect['direction'], $effect['valued_quantity'],
            $effect['direction'], $effect['valued_quantity'],
            $effect['direction'], $effect['quantity'],
            $effect['direction'], $effect['line_value'],
        ])->getRowArray();

        if ($result === null) {
            throw new DomainException('La operación dejaría existencias negativas o una valoración inconsistente.');
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function positiveEffect(array $material, array $stock, string $quantity, ?string $cost, ?string $noCostReason): array
    {
        $lineValue = $cost === null ? '0' : $this->multiply($quantity, $cost);

        return [
            'material'          => $material,
            'stock'             => $stock,
            'direction'         => 1,
            'quantity'          => $quantity,
            'valued_quantity'   => $cost === null ? '0' : $quantity,
            'unit_cost'         => $cost,
            'line_value'        => $lineValue,
            'pending_valuation' => $cost === null,
            'no_cost_reason'     => $noCostReason,
        ];
    }

    /** @return array<string, mixed> */
    private function negativeEffect(array $material, array $stock, string $quantity): array
    {
        $allocation = $this->db->query(<<<'SQL'
SELECT LEAST(?::numeric, ?::numeric) AS valued_quantity,
       ROUND(LEAST(?::numeric, ?::numeric) * COALESCE(?::numeric, 0), 6) AS line_value,
       CASE WHEN LEAST(?::numeric, ?::numeric) = ?::numeric THEN 1 ELSE 0 END AS fully_valued
SQL, [
            $quantity, $stock['valued_quantity'],
            $quantity, $stock['valued_quantity'], $stock['average_unit_cost'],
            $quantity, $stock['valued_quantity'], $quantity,
        ])->getRowArray();

        return [
            'material'          => $material,
            'stock'             => $stock,
            'direction'         => -1,
            'quantity'          => $quantity,
            'valued_quantity'   => (string) $allocation['valued_quantity'],
            'unit_cost'         => $allocation['valued_quantity'] > 0 ? $stock['average_unit_cost'] : null,
            'line_value'        => (string) $allocation['line_value'],
            'pending_valuation' => ! filter_var($allocation['fully_valued'], FILTER_VALIDATE_BOOL),
            'no_cost_reason'     => null,
        ];
    }

    /** @return array<string, mixed> */
    private function lockStock(int $materialId, int $warehouseId): array
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query(<<<'SQL'
INSERT INTO inventory_stocks
    (material_id, warehouse_id, quantity, valued_quantity, average_unit_cost, total_value, version, created_at, updated_at)
VALUES (?, ?, 0, 0, NULL, 0, 0, ?, ?)
ON CONFLICT (material_id, warehouse_id) DO NOTHING
SQL, [$materialId, $warehouseId, $now, $now]);

        $stock = $this->db->query(
            'SELECT * FROM inventory_stocks WHERE material_id = ? AND warehouse_id = ? FOR UPDATE',
            [$materialId, $warehouseId],
        )->getRowArray();
        if ($stock === null) {
            throw new DomainException('No fue posible bloquear la existencia solicitada.');
        }

        return $stock;
    }

    /** @return array<string, mixed> */
    private function activeMaterial(int $materialId, bool $requireActive = true): array
    {
        $builder = $this->db->table('materials')->where('id', $materialId);
        if ($requireActive) {
            $builder->where('active', true);
        }
        $material = $builder->get()->getRowArray();
        if ($material === null) {
            throw new DomainException('Uno de los materiales no está disponible.');
        }

        return $material;
    }

    private function assertActiveWarehouse(int $warehouseId): void
    {
        if ($warehouseId <= 0 || $this->db->table('warehouses')->where('id', $warehouseId)->where('active', true)->countAllResults() !== 1) {
            throw new DomainException('La bodega seleccionada no está disponible.');
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function sortAndRejectDuplicates(array $items): array
    {
        usort($items, static fn (array $left, array $right): int => (int) ($left['material_id'] ?? 0) <=> (int) ($right['material_id'] ?? 0));
        $seen = [];
        foreach ($items as $item) {
            $materialId = (int) ($item['material_id'] ?? 0);
            if ($materialId <= 0 || isset($seen[$materialId])) {
                throw new DomainException('Cada material debe aparecer una sola vez en el movimiento.');
            }
            $seen[$materialId] = true;
        }

        return $items;
    }

    /** @param array<string, mixed> $header */
    private function assertResponsibleNames(array $header): void
    {
        foreach (['delivered_by_name', 'received_by_name'] as $field) {
            $value = trim((string) ($header[$field] ?? ''));
            if ($value === '' || mb_strlen($value) > 160) {
                throw new DomainException('Los nombres de quien entrega y quien recibe son obligatorios.');
            }
        }
    }

    private function normalizeOptionalCost(mixed $value): ?string
    {
        $cost = trim((string) $value);
        if ($cost === '') {
            return null;
        }
        if (! preg_match('/\A\d{1,12}(?:\.\d{1,6})?\z/', $cost)) {
            throw new DomainException('El costo unitario debe ser positivo y tener máximo seis decimales.');
        }

        return $cost;
    }

    private function multiply(string $left, string $right): string
    {
        $row = $this->db->query('SELECT ROUND(?::numeric * ?::numeric, 6) AS result', [$left, $right])->getRowArray();

        return (string) $row['result'];
    }

    private function userName(int $userId): string
    {
        $user = $this->db->table('users')->select('username')->where('id', $userId)->where('active', 1)->get()->getRowArray();
        if ($user === null) {
            throw new DomainException('El usuario no está activo.');
        }

        return trim((string) $user['username']) ?: 'Usuario ' . $userId;
    }

    /** @param array<string, mixed> $data */
    private function insertAndReturnId(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    private function reference(string $prefix): string
    {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function begin(): void
    {
        $this->db->transException(true)->transBegin();
    }

    private function commit(): void
    {
        $this->db->transCommit();
    }

    private function rollback(): void
    {
        $this->db->transRollback();
    }
}
