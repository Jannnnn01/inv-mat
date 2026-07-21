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

            $dispatch = $this->db->table('inventory_dispatch_notes')->where('movement_id', $movementId)->get()->getRowArray();
            if ($dispatch !== null && $dispatch['source_dispatch_note_id'] === null) {
                $activeDeliveries = $this->db->query(<<<'SQL'
SELECT COUNT(*) AS total
  FROM inventory_dispatch_notes child
 WHERE child.source_dispatch_note_id = ?
   AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = child.movement_id)
SQL, [$dispatch['id']])->getRowArray();
                if ((int) ($activeDeliveries['total'] ?? 0) > 0) {
                    throw new DomainException('Primero deben revertirse las entregas posteriores asociadas a esta guía.');
                }
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
        if ($type === 'EXIT'
            && ! empty($header['start_date'])
            && ! empty($header['end_date'])
            && (string) $header['end_date'] < (string) $header['start_date']) {
            throw new DomainException('La fecha de fin de la guía no puede ser anterior a la fecha de inicio.');
        }

        $this->begin();
        try {
            $this->assertActiveWarehouse($warehouseId);
            if ($type === 'ENTRY' && ! empty($header['supplier_id'])) {
                $supplier = $this->db->table('suppliers')->where('id', (int) $header['supplier_id'])->where('active', true)->get()->getRowArray();
                if ($supplier === null) {
                    throw new DomainException('El proveedor seleccionado no está disponible.');
                }
            }

            $dispatchItems = [];
            if ($type === 'EXIT') {
                $requestId = ! empty($header['dispatch_request_id']) ? (int) $header['dispatch_request_id'] : null;
                if ($requestId !== null) {
                    $request = $this->db->query('SELECT * FROM dispatch_requests WHERE id = ? FOR UPDATE', [$requestId])->getRowArray();
                    if ($request === null || $request['status'] !== 'APPROVED' || (int) $request['warehouse_id'] !== $warehouseId) {
                        throw new DomainException('La requisición no está aprobada o no pertenece a la bodega seleccionada.');
                    }
                    $header['recipient_id'] = $request['recipient_id'];
                }
                if (! empty($header['recipient_id'])) {
                    $recipient = $this->db->table('recipients')->where('id', (int) $header['recipient_id'])->where('active', true)->get()->getRowArray();
                    if ($recipient === null) {
                        throw new DomainException('El destinatario seleccionado no está disponible.');
                    }
                    $header['destination_name'] = $recipient['name'];
                    $header['destination_identifier'] = $recipient['document_number'];
                    $header['destination_address'] = $recipient['address'];
                    $header['route_description'] = $recipient['route'];
                }
                if (empty($header['document_number'])) {
                    $header['document_number'] = $this->nextGuideNumber();
                }
                if (empty($header['document_date'])) {
                    $header['document_date'] = date('Y-m-d');
                }
                $sourceDispatchId = ! empty($header['source_dispatch_note_id']) ? (int) $header['source_dispatch_note_id'] : null;
                $prepared = $this->prepareDispatchEffects($warehouseId, $items, $sourceDispatchId, $requestId);
                $effects = $prepared['effects'];
                $dispatchItems = $prepared['lines'];
            } else {
                $effects = $this->prepareOperationalEffects($type, $warehouseId, $items);
            }
            $movementId = $this->insertMovement($type, $warehouseId, null, $header, $effects, $userId);
            if ($type === 'EXIT') {
                $dispatchId = $this->insertDispatchNote($movementId, $header, $dispatchItems, $userId);
                if (! empty($header['dispatch_request_id'])) {
                    $this->db->table('dispatch_requests')->where('id', (int) $header['dispatch_request_id'])->update([
                        'status' => 'CONVERTED', 'dispatch_note_id' => $dispatchId, 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
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
     * @return array{effects: list<array<string, mixed>>, lines: list<array<string, mixed>>}
     */
    private function prepareDispatchEffects(int $warehouseId, array $items, ?int $sourceDispatchId = null, ?int $requestId = null): array
    {
        if ($items === []) {
            throw new DomainException('Agrega al menos un material a la guía.');
        }

        $items = $this->sortAndRejectDuplicates($items);
        if ($sourceDispatchId !== null) {
            $sourceNote = $this->db->query(<<<'SQL'
SELECT dn.id
  FROM inventory_dispatch_notes dn
  JOIN inventory_movements mv ON mv.id = dn.movement_id
 WHERE dn.id = ? AND dn.source_dispatch_note_id IS NULL AND mv.warehouse_id = ?
   AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = mv.id)
SQL, [$sourceDispatchId, $warehouseId])->getRowArray();
            if ($sourceNote === null) {
                throw new DomainException('La guía original no existe, ya es una entrega posterior o pertenece a otra bodega.');
            }
        }

        $effects = [];
        $lines = [];
        foreach ($items as $item) {
            $material = $this->activeMaterial((int) ($item['material_id'] ?? 0));
            $delivered = trim((string) ($item['quantity'] ?? ''));
            $delivered = $delivered === '' ? '0' : $delivered;
            $quantityService = new QuantityService();

            $sourceItemId = null;
            if ($sourceDispatchId !== null) {
                $sourceItemId = (int) ($item['source_dispatch_item_id'] ?? 0);
                $sourceItem = $this->db->query(<<<'SQL'
SELECT *
  FROM inventory_dispatch_items
 WHERE id = ? AND dispatch_note_id = ? AND source_dispatch_item_id IS NULL
 FOR UPDATE
SQL, [$sourceItemId, $sourceDispatchId])->getRowArray();
                if ($sourceItem === null || (int) $sourceItem['material_id'] !== (int) $material['id']) {
                    throw new DomainException('Uno de los pendientes no pertenece a la guía original.');
                }
                $deliveredLater = $this->db->query(<<<'SQL'
SELECT COALESCE(SUM(child.delivered_quantity), 0) AS quantity
  FROM inventory_dispatch_items child
  JOIN inventory_dispatch_notes child_note ON child_note.id = child.dispatch_note_id
 WHERE child.source_dispatch_item_id = ?
   AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = child_note.movement_id)
SQL,
                    [$sourceItemId],
                )->getRowArray();
                $remainingRow = $this->db->query(
                    'SELECT ?::numeric - ?::numeric - ?::numeric AS quantity',
                    [$sourceItem['requested_quantity'], $sourceItem['delivered_quantity'], $deliveredLater['quantity']],
                )->getRowArray();
                $requested = (string) $remainingRow['quantity'];
                if ((float) $requested <= 0) {
                    throw new DomainException('Uno de los pendientes ya fue entregado completamente.');
                }
            } else {
                $requested = trim((string) ($item['requested_quantity'] ?? $item['quantity'] ?? ''));
                if ($requestId !== null) {
                    $requestItemId = (int) ($item['request_item_id'] ?? 0);
                    $requestItem = $this->db->query('SELECT * FROM dispatch_request_items WHERE id = ? AND request_id = ? FOR UPDATE', [$requestItemId, $requestId])->getRowArray();
                    if ($requestItem === null || (int) $requestItem['material_id'] !== (int) $material['id'] || (float) $requestItem['quantity'] !== (float) $requested) {
                        throw new DomainException('Los materiales no coinciden con la requisición aprobada.');
                    }
                    $this->releaseRequestReservation($requestItemId);
                }
            }

            if (! $quantityService->isValid($requested, (bool) $material['allows_fraction']) || (float) $requested <= 0) {
                throw new DomainException('La cantidad solicitada no es válida para ' . $material['name'] . '.');
            }
            if (! $quantityService->isValid($delivered, (bool) $material['allows_fraction'])) {
                throw new DomainException('La cantidad entregada no es válida para ' . $material['name'] . '.');
            }
            if ($sourceDispatchId !== null && (float) $delivered <= 0) {
                throw new DomainException('La cantidad de una entrega posterior debe ser mayor que cero.');
            }

            $comparison = $this->db->query(<<<'SQL'
SELECT CASE
           WHEN ?::numeric = 0 THEN 'PENDING'
           WHEN ?::numeric < ?::numeric THEN 'PARTIAL'
           ELSE 'DELIVERED'
       END AS status,
       CASE WHEN ?::numeric <= ?::numeric THEN 1 ELSE 0 END AS allowed
SQL, [$delivered, $delivered, $requested, $delivered, $requested])->getRowArray();
            if ((int) $comparison['allowed'] !== 1) {
                throw new DomainException('La cantidad entregada no puede superar la solicitada para ' . $material['name'] . '.');
            }

            $stock = null;
            if ($sourceItemId === null) {
                $stock = $this->lockStock((int) $material['id'], $warehouseId);
                if ((float) $requested > (float) $stock['quantity'] - $this->activeReserved($warehouseId, (int) $material['id'])) {
                    throw new DomainException('La cantidad solicitada supera la existencia disponible de ' . $material['name'] . '.');
                }
            }
            if ((float) $delivered > 0) {
                if ($sourceItemId !== null) {
                    $this->consumeDispatchReservation($sourceItemId, $delivered);
                    $stock = $this->lockStock((int) $material['id'], $warehouseId);
                }
                $effects[] = $this->negativeEffect($material, $stock, $delivered);
            }
            $lines[] = [
                'material_id'        => (int) $material['id'],
                'source_dispatch_item_id' => $sourceItemId,
                'requested_quantity' => $requested,
                'delivered_quantity' => $delivered,
                'status'             => $comparison['status'],
            ];
        }

        if ($effects === []) {
            throw new DomainException('La guía debe entregar al menos un material. Las solicitudes completamente pendientes se registrarán en un módulo de requisiciones.');
        }

        return ['effects' => $effects, 'lines' => $lines];
    }

    /** @param list<array<string, mixed>> $lines */
    private function insertDispatchNote(int $movementId, array $header, array $lines, int $userId): int
    {
        $now = date('Y-m-d H:i:s');
        $dispatchId = $this->insertAndReturnId('inventory_dispatch_notes', [
            'movement_id'             => $movementId,
            'source_dispatch_note_id' => ! empty($header['source_dispatch_note_id']) ? (int) $header['source_dispatch_note_id'] : null,
            'dispatch_request_id'     => ! empty($header['dispatch_request_id']) ? (int) $header['dispatch_request_id'] : null,
            'recipient_id'            => ! empty($header['recipient_id']) ? (int) $header['recipient_id'] : null,
            'guide_number'            => $this->nullable($header['document_number'] ?? null),
            'authorization_number'    => $this->nullable($header['authorization_number'] ?? null),
            'issue_date'              => $this->nullable($header['document_date'] ?? null),
            'start_date'              => $this->nullable($header['start_date'] ?? null),
            'end_date'                => $this->nullable($header['end_date'] ?? null),
            'issuer_name'             => $this->nullable($header['issuer_name'] ?? null),
            'issuer_tax_identifier'   => $this->nullable($header['issuer_tax_identifier'] ?? null),
            'transporter_name'        => $this->nullable($header['transporter_name'] ?? null),
            'transporter_identifier'  => $this->nullable($header['transporter_identifier'] ?? null),
            'vehicle_plate'           => $this->nullable($header['vehicle_plate'] ?? null),
            'origin_place'            => $this->nullable($header['origin_place'] ?? null),
            'destination_name'        => $this->nullable($header['destination_name'] ?? null),
            'destination_identifier'  => $this->nullable($header['destination_identifier'] ?? null),
            'destination_address'     => $this->nullable($header['destination_address'] ?? null),
            'route_description'       => $this->nullable($header['route_description'] ?? null),
            'related_document_number' => $this->nullable($header['related_document_number'] ?? null),
            'description'             => $this->nullable($header['dispatch_description'] ?? null),
            'created_at'              => $now,
        ]);

        foreach ($lines as $line) {
            $this->db->table('inventory_dispatch_items')->insert($line + [
                'dispatch_note_id' => $dispatchId,
                'created_at'       => $now,
            ]);
            $dispatchItemId = (int) $this->db->insertID();
            $pending = round((float) $line['requested_quantity'] - (float) $line['delivered_quantity'], 3);
            if ($pending > 0 && $line['source_dispatch_item_id'] === null) {
                $pendingQuantity = number_format($pending, 3, '.', '');
                $this->db->table('inventory_reservations')->insert([
                    'warehouse_id' => (int) $header['warehouse_id'], 'material_id' => (int) $line['material_id'],
                    'request_item_id' => null, 'dispatch_item_id' => $dispatchItemId,
                    'initial_quantity' => $pendingQuantity, 'remaining_quantity' => $pendingQuantity,
                    'status' => 'ACTIVE', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        return $dispatchId;
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

        $this->restoreOrReleaseDispatchReservations($originalId);

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
        $available = (float) $stock['quantity'] - $this->activeReserved((int) $stock['warehouse_id'], (int) $material['id']);
        if ((float) $quantity > $available) {
            throw new DomainException('La operación supera el stock disponible no reservado de ' . $material['name'] . '.');
        }
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
        if (! preg_match('/\A\d{1,12}(?:\.\d{1,6})?\z/', $cost) || (float) $cost <= 0) {
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

    private function nextGuideNumber(): string
    {
        $row = $this->db->query(<<<'SQL'
INSERT INTO document_sequences (series_key, establishment_code, emission_point_code, next_number, updated_at)
VALUES ('DISPATCH', '001', '001', 2, ?)
ON CONFLICT (series_key) DO UPDATE
SET next_number = document_sequences.next_number + 1, updated_at = EXCLUDED.updated_at
RETURNING establishment_code, emission_point_code, next_number - 1 AS sequence
SQL, [date('Y-m-d H:i:s')])->getRowArray();
        if ($row === null) {
            throw new DomainException('No fue posible generar el número de guía.');
        }

        return $row['establishment_code'] . '-' . $row['emission_point_code'] . '-' . str_pad((string) $row['sequence'], 9, '0', STR_PAD_LEFT);
    }

    private function activeReserved(int $warehouseId, int $materialId): float
    {
        $row = $this->db->table('inventory_reservations')->selectSum('remaining_quantity', 'quantity')
            ->where('warehouse_id', $warehouseId)->where('material_id', $materialId)->where('status', 'ACTIVE')->get()->getRowArray();

        return (float) ($row['quantity'] ?? 0);
    }

    private function releaseRequestReservation(int $requestItemId): void
    {
        $reservation = $this->db->query("SELECT * FROM inventory_reservations WHERE request_item_id = ? AND status = 'ACTIVE' FOR UPDATE", [$requestItemId])->getRowArray();
        if ($reservation === null) {
            throw new DomainException('La reserva de la requisición ya no está disponible.');
        }
        $this->db->table('inventory_reservations')->where('id', $reservation['id'])->update([
            'remaining_quantity' => 0, 'status' => 'RELEASED', 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function consumeDispatchReservation(int $dispatchItemId, string $quantity): void
    {
        $reservation = $this->db->query("SELECT * FROM inventory_reservations WHERE dispatch_item_id = ? AND status = 'ACTIVE' FOR UPDATE", [$dispatchItemId])->getRowArray();
        if ($reservation === null || (float) $quantity > (float) $reservation['remaining_quantity']) {
            throw new DomainException('La entrega supera la reserva vigente de la guía.');
        }
        $remaining = round((float) $reservation['remaining_quantity'] - (float) $quantity, 3);
        $this->db->table('inventory_reservations')->where('id', $reservation['id'])->update([
            'remaining_quantity' => $remaining, 'status' => $remaining <= 0 ? 'CONSUMED' : 'ACTIVE',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function restoreOrReleaseDispatchReservations(int $movementId): void
    {
        $dispatch = $this->db->table('inventory_dispatch_notes')->where('movement_id', $movementId)->get()->getRowArray();
        if ($dispatch === null) {
            return;
        }

        $items = $this->db->table('inventory_dispatch_items')->where('dispatch_note_id', $dispatch['id'])->get()->getResultArray();
        if ($dispatch['source_dispatch_note_id'] === null) {
            foreach ($items as $item) {
                $this->db->table('inventory_reservations')->where('dispatch_item_id', $item['id'])->where('status', 'ACTIVE')->update([
                    'remaining_quantity' => 0,
                    'status' => 'RELEASED',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return;
        }

        foreach ($items as $item) {
            $rootItemId = (int) $item['source_dispatch_item_id'];
            $reservation = $this->db->query('SELECT * FROM inventory_reservations WHERE dispatch_item_id = ? FOR UPDATE', [$rootItemId])->getRowArray();
            if ($reservation === null) {
                throw new DomainException('No se encontró la reserva original de la entrega posterior.');
            }
            $remaining = round((float) $reservation['remaining_quantity'] + (float) $item['delivered_quantity'], 3);
            if ($remaining > (float) $reservation['initial_quantity']) {
                throw new DomainException('La reversión excedería la reserva original de la guía.');
            }
            $this->db->table('inventory_reservations')->where('id', $reservation['id'])->update([
                'remaining_quantity' => $remaining,
                'status' => 'ACTIVE',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
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
