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
        if ($user === null || $warehouse === null || $unit === null) {
            CLI::error('Faltan datos base para ejecutar la prueba.');
            return;
        }

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $now = date('Y-m-d H:i:s');
        $db->transException(true)->transBegin();
        try {
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
            $exitMovementId = $service->createExit($header + ['reason' => 'Prueba de salida'], [['material_id' => $materialId, 'quantity' => '12.000']], (int) $user['id']);

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

            $reversalRequestId = $service->requestReversal($exitMovementId, 'Reversión temporal de prueba', (int) $user['id']);
            $service->decideRequest($reversalRequestId, true, 'Reversión verificada', $approverId);
            $valuationService->correct(
                $valuationId,
                '5.000000',
                'Corrección temporal de costo',
                (int) $user['id'],
            );

            $stock = $db->table('inventory_stocks')->where('material_id', $materialId)->where('warehouse_id', $warehouse['id'])->get()->getRowArray();
            $passed = $stock !== null
                && (string) $stock['quantity'] === '17.000'
                && (string) $stock['valued_quantity'] === '15.000'
                && (float) $stock['total_value'] === 46.0
                && $db->table('inventory_movements')->whereIn('id', [$firstMovementId, $exitMovementId])->countAllResults() === 2
                && $db->table('inventory_requests')->whereIn('id', [$adjustmentRequestId, $reversalRequestId])->where('status', 'EXECUTED')->countAllResults() === 2
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

            CLI::write('SMOKE_OK: movimientos, valoración, corrección, ajuste, segregación, reversión e inmutabilidad verificados.', 'green');
        } finally {
            $db->transRollback();
        }
    }
}
