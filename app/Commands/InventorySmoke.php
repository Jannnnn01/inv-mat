<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\InventoryMovementService;
use App\Services\InventoryValuationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Exceptions\DatabaseException;
use DomainException;

final class InventorySmoke extends BaseCommand
{
    protected $group = 'Testing';
    protected $name = 'invmat:inventory:smoke';
    protected $description = 'Prueba transaccional reversible del núcleo de inventario.';

    public function run(array $params): void
    {
        $db = db_connect();
        $user = $db->table('users')->where('active', 1)->orderBy('id')->get()->getRowArray();
        $warehouse = $db->table('warehouses')->where('active', true)->orderBy('id')->get()->getRowArray();
        $unit = $db->table('measurement_units')->where('active', true)->orderBy('id')->get()->getRowArray();
        if ($warehouse === null || $unit === null) {
            CLI::error('Faltan datos base para ejecutar la prueba.');
            return;
        }

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $now = date('Y-m-d H:i:s');
        $db->transException(true)->transBegin();
        try {
            if ($user === null) {
                $db->table('users')->insert([
                    'username' => 'smoke-user-' . strtolower($suffix),
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $user = ['id' => (int) $db->insertID()];
            }

            $db->table('categories')->insert([
                'code' => 'SMOKE-' . $suffix, 'name' => 'Prueba temporal ' . $suffix,
                'active' => true, 'created_by' => $user['id'], 'updated_by' => $user['id'],
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $categoryId = (int) $db->insertID();
            $db->table('materials')->insert([
                'code' => 'SMOKE-' . $suffix, 'name' => 'Material temporal ' . $suffix,
                'category_id' => $categoryId, 'unit_id' => $unit['id'], 'allows_fraction' => true,
                'minimum_stock' => '1.000', 'active' => true, 'created_by' => $user['id'], 'updated_by' => $user['id'],
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $materialId = (int) $db->insertID();
            $db->table('users')->insert([
                'username' => 'smoke-approver-' . strtolower($suffix),
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $approverId = (int) $db->insertID();
            $header = [
                'warehouse_id' => $warehouse['id'],
                'delivered_by_name' => 'Prueba automatizada',
                'received_by_name' => 'Prueba automatizada',
            ];
            $service = new InventoryMovementService($db);
            $firstMovementId = $service->createEntry($header, [['material_id' => $materialId, 'quantity' => '10.000', 'unit_cost' => '2.500000']], (int) $user['id']);
            $pendingMovementId = $service->createEntry($header, [['material_id' => $materialId, 'quantity' => '5.000', 'unit_cost' => null, 'no_cost_reason' => 'Prueba sin valoración']], (int) $user['id']);
            $exitMovementId = $service->createExit(
                $header + ['reason' => 'Prueba de salida', 'document_number' => 'GUIA-' . $suffix],
                [['material_id' => $materialId, 'requested_quantity' => '15.000', 'quantity' => '12.000']],
                (int) $user['id'],
            );
            $sourceDispatch = $db->table('inventory_dispatch_notes')->where('movement_id', $exitMovementId)->get()->getRowArray();
            $sourceDispatchItem = $db->table('inventory_dispatch_items')->where('dispatch_note_id', $sourceDispatch['id'])->get()->getRowArray();

            $pendingItem = $db->table('inventory_movement_items')->where('movement_id', $pendingMovementId)->get()->getRowArray();
            $valuationService = new InventoryValuationService($db);
            $valuationId = $valuationService->complete(
                (int) $pendingItem['id'],
                '5.000',
                '4.000000',
                'Valoración temporal completa',
                (int) $user['id'],
            );

            $adjustmentRequestId = $service->requestAdjustment(
                (int) $warehouse['id'],
                'Ajuste temporal de prueba',
                [['material_id' => $materialId, 'quantity_delta' => '2.000', 'unit_cost' => '3.000000']],
                (int) $user['id'],
            );
            try {
                $service->decideRequest($adjustmentRequestId, true, 'Autoaprobación no permitida', (int) $user['id']);
                throw new \RuntimeException('La autoaprobación fue aceptada incorrectamente.');
            } catch (DomainException) {
                // Resultado esperado: solicitante y aprobador deben ser diferentes.
            }
            $service->decideRequest($adjustmentRequestId, true, 'Ajuste verificado', $approverId);

            $valuationService->correct(
                $valuationId,
                '5.000000',
                'Corrección temporal de costo',
                (int) $user['id'],
            );

            $followupMovementId = $service->createExit(
                $header + [
                    'reason'                  => 'Entrega posterior de prueba',
                    'document_number'         => 'GUIA-2-' . $suffix,
                    'source_dispatch_note_id' => $sourceDispatch['id'],
                ],
                [[
                    'material_id'             => $materialId,
                    'source_dispatch_item_id' => $sourceDispatchItem['id'],
                    'quantity'                => '3.000',
                ]],
                (int) $user['id'],
            );
            $pendingAfterDelivery = $db->query(
                'SELECT requested_quantity - delivered_quantity - COALESCE((SELECT SUM(delivered_quantity) FROM inventory_dispatch_items child WHERE child.source_dispatch_item_id = root.id), 0) AS quantity FROM inventory_dispatch_items root WHERE id = ?',
                [$sourceDispatchItem['id']],
            )->getRowArray();
            if ((string) $pendingAfterDelivery['quantity'] !== '0.000') {
                throw new \RuntimeException('La entrega posterior no cerró el pendiente original.');
            }
            $followupReversalId = $service->requestReversal($followupMovementId, 'Reversión de entrega posterior', (int) $user['id']);
            $service->decideRequest($followupReversalId, true, 'Reabre pendiente de prueba', $approverId);
            $pendingAfterReversal = $db->query(<<<'SQL'
SELECT root.requested_quantity - root.delivered_quantity - COALESCE((
    SELECT SUM(child.delivered_quantity)
      FROM inventory_dispatch_items child
      JOIN inventory_dispatch_notes child_note ON child_note.id = child.dispatch_note_id
     WHERE child.source_dispatch_item_id = root.id
       AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = child_note.movement_id)
), 0) AS quantity
  FROM inventory_dispatch_items root
 WHERE root.id = ?
SQL, [$sourceDispatchItem['id']])->getRowArray();
            if ((string) $pendingAfterReversal['quantity'] !== '3.000') {
                throw new \RuntimeException('La reversión no reabrió el pendiente original.');
            }

            $reversalRequestId = $service->requestReversal($exitMovementId, 'Reversión temporal de prueba', (int) $user['id']);
            $service->decideRequest($reversalRequestId, true, 'Reversión verificada', $approverId);

            $stock = $db->table('inventory_stocks')->where('material_id', $materialId)->where('warehouse_id', $warehouse['id'])->get()->getRowArray();
            $passed = $stock !== null
                && (string) $stock['quantity'] === '17.000'
                && (string) $stock['valued_quantity'] === '15.000'
                && (float) $stock['total_value'] === 46.0
                && $db->table('inventory_movements')->whereIn('id', [$firstMovementId, $exitMovementId])->countAllResults() === 2
                && $db->table('inventory_dispatch_notes')->where('movement_id', $exitMovementId)->where('guide_number', 'GUIA-' . $suffix)->countAllResults() === 1
                && $db->table('inventory_dispatch_items di')->join('inventory_dispatch_notes dn', 'dn.id = di.dispatch_note_id')->where('dn.movement_id', $exitMovementId)->where('di.status', 'PARTIAL')->where('di.requested_quantity', '15.000')->where('di.delivered_quantity', '12.000')->countAllResults() === 1
                && $db->table('inventory_requests')->whereIn('id', [$adjustmentRequestId, $reversalRequestId, $followupReversalId])->where('status', 'EXECUTED')->countAllResults() === 3
                && $db->table('inventory_dispatch_notes')->where('movement_id', $followupMovementId)->where('source_dispatch_note_id', $sourceDispatch['id'])->countAllResults() === 1
                && $db->table('inventory_valuation_events')->where('movement_item_id', $pendingItem['id'])->countAllResults() === 2;
            if (! $passed) {
                throw new \RuntimeException('El saldo transaccional no coincide con el esperado.');
            }

            $immutable = false;
            try {
                $db->table('inventory_movements')->where('id', $firstMovementId)->update(['reason' => 'Cambio prohibido']);
            } catch (DatabaseException) {
                $immutable = true;
            }
            if (! $immutable) {
                throw new \RuntimeException('La base permitió modificar un movimiento histórico.');
            }

            CLI::write('SMOKE_OK: movimientos, guía parcial, entrega posterior, reapertura por reversión, valoración, ajuste, segregación e inmutabilidad verificados.', 'green');
        } finally {
            $db->transRollback();
        }
    }
}
