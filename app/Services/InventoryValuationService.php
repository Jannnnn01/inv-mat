<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use Throwable;

final class InventoryValuationService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $database = null)
    {
        $this->db = $database ?? db_connect();
    }

    public function complete(int $movementItemId, string $quantity, string $unitCost, string $reason, int $userId): int
    {
        $reason = $this->validReason($reason);
        $unitCost = $this->validCost($unitCost);
        $this->begin();
        try {
            $item = $this->pendingItem($movementItemId);
            $quantity = trim($quantity);
            if (! (new QuantityService())->isValid($quantity, (bool) $item['allows_fraction']) || (float) $quantity <= 0) {
                throw new DomainException('La cantidad que se desea valorar no es válida.');
            }

            $remaining = $this->db->query(<<<'SQL'
SELECT i.quantity - COALESCE(SUM(v.quantity_basis) FILTER (WHERE v.event_type = 'ALLOCATION'), 0) AS remaining,
       CASE WHEN ?::numeric <= i.quantity - COALESCE(SUM(v.quantity_basis) FILTER (WHERE v.event_type = 'ALLOCATION'), 0) THEN 1 ELSE 0 END AS allowed
  FROM inventory_movement_items i
  LEFT JOIN inventory_valuation_events v ON v.movement_item_id = i.id
 WHERE i.id = ?
 GROUP BY i.id, i.quantity
SQL, [$quantity, $movementItemId])->getRowArray();
            if ($remaining === null || (int) $remaining['allowed'] !== 1) {
                throw new DomainException('La cantidad supera la valoración pendiente del movimiento.');
            }

            $stock = $this->lockStock((int) $item['material_id'], (int) $item['warehouse_id']);
            $split = $this->db->query(<<<'SQL'
SELECT LEAST(?::numeric, quantity - valued_quantity) AS inventory_quantity,
       ?::numeric - LEAST(?::numeric, quantity - valued_quantity) AS consumed_quantity
  FROM inventory_stocks
 WHERE id = ?
SQL, [$quantity, $quantity, $quantity, $stock['id']])->getRowArray();
            $inventoryQuantity = (string) $split['inventory_quantity'];
            $consumedQuantity = (string) $split['consumed_quantity'];
            $inventoryValue = $this->multiply($inventoryQuantity, $unitCost);
            $consumedValue = $this->multiply($consumedQuantity, $unitCost);
            $this->increaseStockValuation((int) $stock['id'], $inventoryQuantity, $inventoryValue);

            $valuationId = $this->insertEvent([
                'event_type'               => 'ALLOCATION',
                'movement_item_id'          => $movementItemId,
                'original_valuation_id'     => null,
                'material_id'               => $item['material_id'],
                'warehouse_id'              => $item['warehouse_id'],
                'quantity_basis'            => $quantity,
                'inventory_quantity_basis'  => $inventoryQuantity,
                'consumed_quantity_basis'   => $consumedQuantity,
                'previous_unit_cost'        => null,
                'unit_cost'                 => $unitCost,
                'inventory_value_delta'     => $inventoryValue,
                'consumed_value_delta'      => $consumedValue,
                'reason'                    => $reason,
                'created_by'                => $userId,
            ]);
            $this->commit();

            return $valuationId;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function correct(int $valuationId, string $newUnitCost, string $reason, int $userId): int
    {
        $reason = $this->validReason($reason);
        $newUnitCost = $this->validCost($newUnitCost);
        $this->begin();
        try {
            $original = $this->db->query(
                "SELECT * FROM inventory_valuation_events WHERE id = ? AND event_type = 'ALLOCATION' FOR UPDATE",
                [$valuationId],
            )->getRowArray();
            if ($original === null) {
                throw new DomainException('La valoración original no existe.');
            }

            $latest = $this->db->query(<<<'SQL'
SELECT unit_cost
  FROM inventory_valuation_events
 WHERE original_valuation_id = ? AND event_type = 'CORRECTION'
 ORDER BY id DESC
 LIMIT 1
SQL, [$valuationId])->getRowArray();
            $previousCost = (string) ($latest['unit_cost'] ?? $original['unit_cost']);
            if ($this->numericEquals($previousCost, $newUnitCost)) {
                throw new DomainException('El nuevo costo debe ser diferente del costo vigente.');
            }

            $stock = $this->lockStock((int) $original['material_id'], (int) $original['warehouse_id']);
            $basis = $this->db->query(<<<'SQL'
SELECT LEAST(?::numeric, valued_quantity) AS inventory_quantity,
       ?::numeric - LEAST(?::numeric, valued_quantity) AS consumed_quantity,
       (?::numeric - ?::numeric) AS cost_delta
  FROM inventory_stocks
 WHERE id = ?
SQL, [
                $original['inventory_quantity_basis'],
                $original['quantity_basis'],
                $original['inventory_quantity_basis'],
                $newUnitCost,
                $previousCost,
                $stock['id'],
            ])->getRowArray();
            $inventoryQuantity = (string) $basis['inventory_quantity'];
            $consumedQuantity = (string) $basis['consumed_quantity'];
            $inventoryDelta = $this->multiply($inventoryQuantity, (string) $basis['cost_delta']);
            $consumedDelta = $this->multiply($consumedQuantity, (string) $basis['cost_delta']);
            $this->correctStockValue((int) $stock['id'], $inventoryDelta);

            $correctionId = $this->insertEvent([
                'event_type'               => 'CORRECTION',
                'movement_item_id'          => $original['movement_item_id'],
                'original_valuation_id'     => $valuationId,
                'material_id'               => $original['material_id'],
                'warehouse_id'              => $original['warehouse_id'],
                'quantity_basis'            => $original['quantity_basis'],
                'inventory_quantity_basis'  => $inventoryQuantity,
                'consumed_quantity_basis'   => $consumedQuantity,
                'previous_unit_cost'        => $previousCost,
                'unit_cost'                 => $newUnitCost,
                'inventory_value_delta'     => $inventoryDelta,
                'consumed_value_delta'      => $consumedDelta,
                'reason'                    => $reason,
                'created_by'                => $userId,
            ]);
            $this->commit();

            return $correctionId;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function pendingItem(int $itemId): array
    {
        $item = $this->db->query(<<<'SQL'
SELECT i.*, mv.warehouse_id, m.name AS material_name, m.allows_fraction
  FROM inventory_movement_items i
  JOIN inventory_movements mv ON mv.id = i.movement_id
  JOIN materials m ON m.id = i.material_id
 WHERE i.id = ? AND i.direction = 1 AND i.pending_valuation = TRUE
 FOR UPDATE OF i
SQL, [$itemId])->getRowArray();
        if ($item === null) {
            throw new DomainException('El detalle seleccionado no admite valoración pendiente.');
        }

        return $item;
    }

    /** @return array<string, mixed> */
    private function lockStock(int $materialId, int $warehouseId): array
    {
        $stock = $this->db->query(
            'SELECT * FROM inventory_stocks WHERE material_id = ? AND warehouse_id = ? FOR UPDATE',
            [$materialId, $warehouseId],
        )->getRowArray();
        if ($stock === null) {
            throw new DomainException('No existe el saldo relacionado con esta valoración.');
        }

        return $stock;
    }

    private function increaseStockValuation(int $stockId, string $quantity, string $value): void
    {
        $result = $this->db->query(<<<'SQL'
UPDATE inventory_stocks
   SET valued_quantity = valued_quantity + ?::numeric,
       total_value = total_value + ?::numeric,
       average_unit_cost = CASE WHEN valued_quantity + ?::numeric > 0
           THEN ROUND((total_value + ?::numeric) / (valued_quantity + ?::numeric), 6)
           ELSE NULL END,
       version = version + 1,
       updated_at = ?
 WHERE id = ?
   AND valued_quantity + ?::numeric <= quantity
 RETURNING id
SQL, [$quantity, $value, $quantity, $value, $quantity, date('Y-m-d H:i:s'), $stockId, $quantity])->getRowArray();
        if ($result === null) {
            throw new DomainException('La valoración supera la existencia física disponible.');
        }
    }

    private function correctStockValue(int $stockId, string $valueDelta): void
    {
        $result = $this->db->query(<<<'SQL'
UPDATE inventory_stocks
   SET total_value = total_value + ?::numeric,
       average_unit_cost = CASE WHEN valued_quantity > 0
           THEN ROUND((total_value + ?::numeric) / valued_quantity, 6)
           ELSE NULL END,
       version = version + 1,
       updated_at = ?
 WHERE id = ?
   AND total_value + ?::numeric >= 0
 RETURNING id
SQL, [$valueDelta, $valueDelta, date('Y-m-d H:i:s'), $stockId, $valueDelta])->getRowArray();
        if ($result === null) {
            throw new DomainException('La corrección produciría una valoración negativa.');
        }
    }

    /** @param array<string, mixed> $data */
    private function insertEvent(array $data): int
    {
        $data['valuation_number'] = 'VAL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('inventory_valuation_events')->insert($data);

        return (int) $this->db->insertID();
    }

    private function validReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
            throw new DomainException('El motivo debe tener entre 5 y 1000 caracteres.');
        }

        return $reason;
    }

    private function validCost(string $cost): string
    {
        $cost = trim($cost);
        if (! preg_match('/\A\d{1,12}(?:\.\d{1,6})?\z/', $cost) || (float) $cost <= 0) {
            throw new DomainException('El costo debe ser positivo y tener máximo seis decimales.');
        }

        return $cost;
    }

    private function multiply(string $left, string $right): string
    {
        return (string) $this->db->query(
            'SELECT ROUND(?::numeric * ?::numeric, 6) AS result',
            [$left, $right],
        )->getRowArray()['result'];
    }

    private function numericEquals(string $left, string $right): bool
    {
        return (int) $this->db->query(
            'SELECT CASE WHEN ?::numeric = ?::numeric THEN 1 ELSE 0 END AS equal',
            [$left, $right],
        )->getRowArray()['equal'] === 1;
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
