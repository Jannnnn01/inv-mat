<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Services\InventoryMovementService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

final class DispatchController extends BaseController
{
    public function pending(): string
    {
        $records = db_connect()->query(<<<'SQL'
SELECT dn.id, dn.guide_number, dn.issue_date, mv.id AS movement_id, mv.movement_number,
       w.name AS warehouse_name, COUNT(*) AS pending_lines,
       SUM(di.requested_quantity - di.delivered_quantity - COALESCE((
           SELECT SUM(child.delivered_quantity)
             FROM inventory_dispatch_items child
             JOIN inventory_dispatch_notes child_note ON child_note.id = child.dispatch_note_id
            WHERE child.source_dispatch_item_id = di.id
              AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = child_note.movement_id)
       ), 0)) AS pending_quantity
  FROM inventory_dispatch_notes dn
  JOIN inventory_movements mv ON mv.id = dn.movement_id
  JOIN warehouses w ON w.id = mv.warehouse_id
  JOIN inventory_dispatch_items di ON di.dispatch_note_id = dn.id AND di.source_dispatch_item_id IS NULL
 WHERE dn.source_dispatch_note_id IS NULL
   AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = mv.id)
   AND di.requested_quantity > di.delivered_quantity + COALESCE((
       SELECT SUM(child.delivered_quantity)
         FROM inventory_dispatch_items child
         JOIN inventory_dispatch_notes child_note ON child_note.id = child.dispatch_note_id
        WHERE child.source_dispatch_item_id = di.id
          AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = child_note.movement_id)
   ), 0)
 GROUP BY dn.id, mv.id, w.id
 ORDER BY dn.id DESC
SQL)->getResultArray();

        return view('inventory/dispatches/pending', ['records' => $records]);
    }

    public function newDelivery(int $dispatchId)
    {
        $data = $this->pendingDispatch($dispatchId);
        if ($data === null) {
            return redirect()->route('inventory-dispatch-pending')->with('error', 'La guía no existe o ya no tiene cantidades pendientes.');
        }

        return view('inventory/dispatches/fulfillment', $data);
    }

    public function createDelivery(int $dispatchId): RedirectResponse
    {
        $data = $this->pendingDispatch($dispatchId);
        if ($data === null) {
            return redirect()->route('inventory-dispatch-pending')->with('error', 'La guía no existe o ya no tiene cantidades pendientes.');
        }
        if (! $this->validateData($this->request->getPost(), $this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $sourceIds = (array) $this->request->getPost('source_dispatch_item_id');
        $materialIds = (array) $this->request->getPost('material_id');
        $quantities = (array) $this->request->getPost('quantity');
        $items = [];
        foreach ($sourceIds as $index => $sourceId) {
            $quantity = trim((string) ($quantities[$index] ?? ''));
            if ($quantity === '' || (float) $quantity <= 0) {
                continue;
            }
            $items[] = [
                'source_dispatch_item_id' => $sourceId,
                'material_id'             => $materialIds[$index] ?? null,
                'quantity'                => $quantity,
            ];
        }

        try {
            $movementId = (new InventoryMovementService())->createExit([
                'warehouse_id'              => $data['dispatch']['warehouse_id'],
                'source_dispatch_note_id'   => $dispatchId,
                'document_number'           => $this->request->getPost('document_number'),
                'document_date'             => $this->request->getPost('document_date'),
                'reason'                    => $this->request->getPost('reason'),
                'observations'              => $this->request->getPost('observations'),
                'delivered_by_name'         => trim((string) $this->request->getPost('delivered_by_name')),
                'received_by_name'          => trim((string) $this->request->getPost('received_by_name')),
                'received_by_area_name'     => $this->request->getPost('received_by_area_name'),
                'transporter_name'          => $this->request->getPost('transporter_name'),
                'transporter_identifier'    => $this->request->getPost('transporter_identifier'),
                'vehicle_plate'             => $this->request->getPost('vehicle_plate'),
                'destination_name'          => $this->request->getPost('destination_name'),
                'destination_identifier'    => $this->request->getPost('destination_identifier'),
                'destination_address'       => $this->request->getPost('destination_address'),
                'route_description'         => $this->request->getPost('route_description'),
                'related_document_number'   => $data['dispatch']['guide_number'],
                'dispatch_description'      => 'Entrega posterior vinculada a la guía ' . ($data['dispatch']['guide_number'] ?: $data['dispatch']['movement_number']),
            ], $items, (int) auth()->id());

            return redirect()->route('inventory-movement-show', [$movementId])->with('message', 'La entrega posterior fue registrada y el pendiente fue actualizado.');
        } catch (DomainException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible registrar la entrega posterior: {message}', ['message' => $exception->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'No fue posible registrar la entrega posterior.');
        }
    }

    /** @return array<string, mixed>|null */
    private function pendingDispatch(int $dispatchId): ?array
    {
        $dispatch = db_connect()->query(<<<'SQL'
SELECT dn.*, mv.movement_number, mv.warehouse_id, w.name AS warehouse_name
  FROM inventory_dispatch_notes dn
  JOIN inventory_movements mv ON mv.id = dn.movement_id
  JOIN warehouses w ON w.id = mv.warehouse_id
 WHERE dn.id = ? AND dn.source_dispatch_note_id IS NULL
   AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = mv.id)
SQL, [$dispatchId])->getRowArray();
        if ($dispatch === null) {
            return null;
        }

        $items = db_connect()->query(<<<'SQL'
SELECT di.id AS source_dispatch_item_id, di.material_id, m.code, m.name, u.symbol AS unit_symbol,
       di.requested_quantity,
       di.delivered_quantity + COALESCE((SELECT SUM(child.delivered_quantity) FROM inventory_dispatch_items child JOIN inventory_dispatch_notes child_note ON child_note.id = child.dispatch_note_id WHERE child.source_dispatch_item_id = di.id AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = child_note.movement_id)), 0) AS delivered_quantity,
       di.requested_quantity - di.delivered_quantity - COALESCE((SELECT SUM(child.delivered_quantity) FROM inventory_dispatch_items child JOIN inventory_dispatch_notes child_note ON child_note.id = child.dispatch_note_id WHERE child.source_dispatch_item_id = di.id AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = child_note.movement_id)), 0) AS pending_quantity
  FROM inventory_dispatch_items di
  JOIN materials m ON m.id = di.material_id
  JOIN measurement_units u ON u.id = m.unit_id
 WHERE di.dispatch_note_id = ? AND di.source_dispatch_item_id IS NULL
   AND di.requested_quantity > di.delivered_quantity + COALESCE((SELECT SUM(child.delivered_quantity) FROM inventory_dispatch_items child JOIN inventory_dispatch_notes child_note ON child_note.id = child.dispatch_note_id WHERE child.source_dispatch_item_id = di.id AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = child_note.movement_id)), 0)
 ORDER BY m.name
SQL, [$dispatchId])->getResultArray();
        if ($items === []) {
            return null;
        }

        return ['dispatch' => $dispatch, 'items' => $items];
    }

    /** @return array<string, array{label: string, rules: string}> */
    private function rules(): array
    {
        return [
            'document_number'       => ['label' => 'Nueva guía', 'rules' => 'permit_empty|max_length[80]'],
            'document_date'         => ['label' => 'Fecha', 'rules' => 'permit_empty|valid_date[Y-m-d]'],
            'reason'                => ['label' => 'Motivo', 'rules' => 'required|min_length[5]|max_length[1000]'],
            'observations'          => ['label' => 'Observaciones', 'rules' => 'permit_empty|max_length[2000]'],
            'delivered_by_name'     => ['label' => 'Responsable que entrega', 'rules' => 'required|max_length[160]'],
            'received_by_name'      => ['label' => 'Responsable que recibe', 'rules' => 'required|max_length[160]'],
            'received_by_area_name' => ['label' => 'Área receptora', 'rules' => 'required|max_length[160]'],
            'transporter_name'      => ['label' => 'Transportista', 'rules' => 'permit_empty|max_length[180]'],
            'transporter_identifier'=> ['label' => 'Identificación del transportista', 'rules' => 'permit_empty|max_length[30]'],
            'vehicle_plate'         => ['label' => 'Placa', 'rules' => 'permit_empty|max_length[20]'],
            'destination_name'      => ['label' => 'Destinatario', 'rules' => 'permit_empty|max_length[180]'],
            'destination_identifier'=> ['label' => 'Identificación del destinatario', 'rules' => 'permit_empty|max_length[30]'],
            'destination_address'   => ['label' => 'Dirección', 'rules' => 'permit_empty|max_length[300]'],
            'route_description'     => ['label' => 'Ruta', 'rules' => 'permit_empty|max_length[300]'],
        ];
    }
}
