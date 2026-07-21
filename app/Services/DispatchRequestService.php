<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use Throwable;

final class DispatchRequestService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @param list<array<string, mixed>> $items */
    public function create(int $warehouseId, int $recipientId, string $reason, ?string $observations, array $items, int $userId): int
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
            throw new DomainException('El motivo debe tener entre 5 y 2000 caracteres.');
        }
        if ($items === []) {
            throw new DomainException('Agrega al menos un material.');
        }

        $this->db->transException(true)->transBegin();
        try {
            $this->assertCatalog($warehouseId, $recipientId);
            usort($items, static fn (array $a, array $b): int => (int) ($a['material_id'] ?? 0) <=> (int) ($b['material_id'] ?? 0));
            $normalized = [];
            $seen = [];
            foreach ($items as $item) {
                $materialId = (int) ($item['material_id'] ?? 0);
                if ($materialId < 1 || isset($seen[$materialId])) {
                    throw new DomainException('Cada material debe aparecer una sola vez.');
                }
                $seen[$materialId] = true;
                $material = $this->db->table('materials')->where('id', $materialId)->where('active', true)->get()->getRowArray();
                if ($material === null) {
                    throw new DomainException('Uno de los materiales no está disponible.');
                }
                $quantity = trim((string) ($item['quantity'] ?? ''));
                if (! (new QuantityService())->isValid($quantity, (bool) $material['allows_fraction']) || (float) $quantity <= 0) {
                    throw new DomainException('Cantidad inválida para ' . $material['name'] . '.');
                }
                $normalized[] = ['material_id' => $materialId, 'quantity' => $quantity];
            }

            $now = date('Y-m-d H:i:s');
            $requestId = $this->insertId('dispatch_requests', [
                'request_number' => 'REQ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4))),
                'status' => 'PENDING', 'warehouse_id' => $warehouseId, 'recipient_id' => $recipientId,
                'reason' => $reason, 'observations' => trim((string) $observations) ?: null,
                'requested_by' => $userId, 'requested_by_name' => $this->userName($userId),
                'requested_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($normalized as $item) {
                $this->db->table('dispatch_request_items')->insert($item + ['request_id' => $requestId, 'created_at' => $now]);
            }
            $this->db->transCommit();

            return $requestId;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function decide(int $requestId, bool $approve, string $comment, int $userId): void
    {
        $comment = trim($comment);
        if (! $approve && mb_strlen($comment) < 5) {
            throw new DomainException('Indica el motivo del rechazo.');
        }
        if (mb_strlen($comment) > 1000) {
            throw new DomainException('El comentario no puede superar 1000 caracteres.');
        }

        $this->db->transException(true)->transBegin();
        try {
            $request = $this->db->query('SELECT * FROM dispatch_requests WHERE id = ? FOR UPDATE', [$requestId])->getRowArray();
            if ($request === null || $request['status'] !== 'PENDING') {
                throw new DomainException('La solicitud no existe o ya fue decidida.');
            }
            if ((int) $request['requested_by'] === $userId) {
                throw new DomainException('No puedes aprobar ni rechazar tu propia solicitud.');
            }

            $now = date('Y-m-d H:i:s');
            if ($approve) {
                $items = $this->db->table('dispatch_request_items')->where('request_id', $requestId)->orderBy('material_id')->get()->getResultArray();
                foreach ($items as $item) {
                    $stock = $this->lockStock((int) $item['material_id'], (int) $request['warehouse_id']);
                    $reserved = $this->activeReserved((int) $request['warehouse_id'], (int) $item['material_id']);
                    if ((float) $item['quantity'] > (float) $stock['quantity'] - $reserved) {
                        throw new DomainException('No hay existencia disponible suficiente para aprobar todos los materiales.');
                    }
                    $this->db->table('inventory_reservations')->insert([
                        'warehouse_id' => $request['warehouse_id'], 'material_id' => $item['material_id'],
                        'request_item_id' => $item['id'], 'dispatch_item_id' => null,
                        'initial_quantity' => $item['quantity'], 'remaining_quantity' => $item['quantity'],
                        'status' => 'ACTIVE', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }

            $this->db->table('dispatch_requests')->where('id', $requestId)->update([
                'status' => $approve ? 'APPROVED' : 'REJECTED',
                'decided_by' => $userId, 'decided_by_name' => $this->userName($userId),
                'decided_at' => $now, 'decision_comment' => $comment ?: null, 'updated_at' => $now,
            ]);
            $this->db->transCommit();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function assertCatalog(int $warehouseId, int $recipientId): void
    {
        if ($this->db->table('warehouses')->where('id', $warehouseId)->where('active', true)->countAllResults() !== 1) {
            throw new DomainException('La bodega no está disponible.');
        }
        if ($this->db->table('recipients')->where('id', $recipientId)->where('active', true)->countAllResults() !== 1) {
            throw new DomainException('El destinatario no está disponible.');
        }
    }

    /** @return array<string, mixed> */
    private function lockStock(int $materialId, int $warehouseId): array
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO inventory_stocks (material_id, warehouse_id, quantity, valued_quantity, average_unit_cost, total_value, version, created_at, updated_at) VALUES (?, ?, 0, 0, NULL, 0, 0, ?, ?) ON CONFLICT (material_id, warehouse_id) DO NOTHING', [$materialId, $warehouseId, $now, $now]);
        $stock = $this->db->query('SELECT * FROM inventory_stocks WHERE material_id = ? AND warehouse_id = ? FOR UPDATE', [$materialId, $warehouseId])->getRowArray();
        if ($stock === null) {
            throw new DomainException('No fue posible consultar la existencia.');
        }

        return $stock;
    }

    private function activeReserved(int $warehouseId, int $materialId): float
    {
        $row = $this->db->table('inventory_reservations')->selectSum('remaining_quantity', 'quantity')
            ->where('warehouse_id', $warehouseId)->where('material_id', $materialId)->where('status', 'ACTIVE')->get()->getRowArray();

        return (float) ($row['quantity'] ?? 0);
    }

    private function userName(int $userId): string
    {
        $user = $this->db->table('users')->select('username')->where('id', $userId)->where('active', 1)->get()->getRowArray();
        if ($user === null) {
            throw new DomainException('El usuario no está activo.');
        }

        return trim((string) $user['username']) ?: 'Usuario ' . $userId;
    }

    private function insertId(string $table, array $data): int
    {
        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }
}
