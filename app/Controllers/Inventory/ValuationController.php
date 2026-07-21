<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Services\InventoryValuationService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

final class ValuationController extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $pending = $db->query(<<<'SQL'
SELECT i.id AS movement_item_id, i.quantity, i.no_cost_reason,
       i.quantity - COALESCE(SUM(v.quantity_basis) FILTER (WHERE v.event_type = 'ALLOCATION'), 0) AS remaining_quantity,
       mv.id AS movement_id, mv.movement_number, mv.created_at, w.name AS warehouse_name,
       m.code AS material_code, m.name AS material_name, u.symbol AS unit_symbol
  FROM inventory_movement_items i
  JOIN inventory_movements mv ON mv.id = i.movement_id
  JOIN warehouses w ON w.id = mv.warehouse_id
  JOIN materials m ON m.id = i.material_id
  JOIN measurement_units u ON u.id = m.unit_id
  LEFT JOIN inventory_valuation_events v ON v.movement_item_id = i.id
 WHERE i.direction = 1 AND i.pending_valuation = TRUE
 GROUP BY i.id, mv.id, w.id, m.id, u.id
HAVING i.quantity > COALESCE(SUM(v.quantity_basis) FILTER (WHERE v.event_type = 'ALLOCATION'), 0)
 ORDER BY mv.id, m.name
SQL)->getResultArray();

        $history = $db->query(<<<'SQL'
SELECT v.*, m.code AS material_code, m.name AS material_name, w.name AS warehouse_name,
       COALESCE((SELECT c.unit_cost FROM inventory_valuation_events c WHERE c.original_valuation_id = v.id AND c.event_type = 'CORRECTION' ORDER BY c.id DESC LIMIT 1), v.unit_cost) AS effective_unit_cost,
       u.username AS created_by_name, mv.id AS movement_id, mv.movement_number
  FROM inventory_valuation_events v
  JOIN materials m ON m.id = v.material_id
  JOIN warehouses w ON w.id = v.warehouse_id
  JOIN users u ON u.id = v.created_by
  JOIN inventory_movement_items i ON i.id = v.movement_item_id
  JOIN inventory_movements mv ON mv.id = i.movement_id
 ORDER BY v.id DESC
 LIMIT 100
SQL)->getResultArray();

        return view('inventory/valuations/index', [
            'pending' => $pending,
            'history' => $history,
        ]);
    }

    public function complete(int $movementItemId): RedirectResponse
    {
        try {
            (new InventoryValuationService())->complete(
                $movementItemId,
                (string) $this->request->getPost('quantity'),
                (string) $this->request->getPost('unit_cost'),
                (string) $this->request->getPost('reason'),
                (int) auth()->id(),
            );

            return redirect()->route('inventory-valuations')->with('message', 'La valoración fue registrada sin modificar el movimiento original.');
        } catch (DomainException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible completar la valoración: {message}', ['message' => $exception->getMessage()]);

            return redirect()->back()->with('error', 'No fue posible registrar la valoración.');
        }
    }

    public function correct(int $valuationId): RedirectResponse
    {
        try {
            (new InventoryValuationService())->correct(
                $valuationId,
                (string) $this->request->getPost('unit_cost'),
                (string) $this->request->getPost('reason'),
                (int) auth()->id(),
            );

            return redirect()->route('inventory-valuations')->with('message', 'La corrección compensatoria fue registrada.');
        } catch (DomainException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible corregir la valoración: {message}', ['message' => $exception->getMessage()]);

            return redirect()->back()->with('error', 'No fue posible registrar la corrección.');
        }
    }
}
