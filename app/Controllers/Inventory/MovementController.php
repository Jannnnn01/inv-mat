<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Models\InventoryMovementItemModel;
use App\Models\InventoryMovementModel;
use App\Models\MaterialModel;
use App\Models\SupplierModel;
use App\Models\WarehouseModel;
use App\Services\InventoryMovementService;
use CodeIgniter\Database\RawSql;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

final class MovementController extends BaseController
{
    public function index(): string
    {
        $type = strtoupper(trim((string) $this->request->getGet('type')));
        $warehouseId = (int) $this->request->getGet('warehouse_id');
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $model = model(InventoryMovementModel::class)
            ->select('inventory_movements.*, warehouses.name AS warehouse_name, users.username AS created_by_name')
            ->select(new RawSql('CASE WHEN EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = inventory_movements.id) THEN 1 ELSE 0 END AS is_reversed'))
            ->select(new RawSql("CASE WHEN EXISTS (SELECT 1 FROM inventory_movement_items pending_item WHERE pending_item.movement_id = inventory_movements.id AND pending_item.pending_valuation = TRUE AND (pending_item.direction = -1 OR pending_item.quantity > COALESCE((SELECT SUM(v.quantity_basis) FROM inventory_valuation_events v WHERE v.movement_item_id = pending_item.id AND v.event_type = 'ALLOCATION'), 0))) THEN 1 ELSE 0 END AS current_pending_valuation"))
            ->select(new RawSql("CASE WHEN EXISTS (SELECT 1 FROM inventory_dispatch_notes dn JOIN inventory_dispatch_items di ON di.dispatch_note_id = dn.id WHERE dn.movement_id = inventory_movements.id AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = inventory_movements.id) AND ((di.source_dispatch_item_id IS NULL AND di.requested_quantity > di.delivered_quantity + COALESCE((SELECT SUM(child.delivered_quantity) FROM inventory_dispatch_items child JOIN inventory_dispatch_notes child_note ON child_note.id = child.dispatch_note_id WHERE child.source_dispatch_item_id = di.id AND NOT EXISTS (SELECT 1 FROM inventory_movements child_reversal WHERE child_reversal.original_movement_id = child_note.movement_id)), 0)) OR (di.source_dispatch_item_id IS NOT NULL AND di.status <> 'DELIVERED'))) THEN 1 ELSE 0 END AS has_pending_dispatch"))
            ->join('warehouses', 'warehouses.id = inventory_movements.warehouse_id')
            ->join('users', 'users.id = inventory_movements.created_by');

        if (in_array($type, ['ENTRY', 'EXIT', 'ADJUSTMENT', 'REVERSAL'], true)) {
            $model->where('inventory_movements.type', $type);
        }
        if ($warehouseId > 0) {
            $model->where('inventory_movements.warehouse_id', $warehouseId);
        }
        if ($from !== '') {
            $model->where('inventory_movements.created_at >=', $from . ' 00:00:00');
        }
        if ($to !== '') {
            $model->where('inventory_movements.created_at <=', $to . ' 23:59:59');
        }

        return view('inventory/movements/index', [
            'records'    => $model->orderBy('inventory_movements.id', 'DESC')->paginate(25),
            'pager'      => $model->pager,
            'warehouses' => model(WarehouseModel::class)->orderBy('name')->findAll(),
            'filters'    => compact('type', 'warehouseId', 'from', 'to'),
        ]);
    }

    public function show(int $id)
    {
        $movement = model(InventoryMovementModel::class)
            ->select('inventory_movements.*, warehouses.name AS warehouse_name, suppliers.name AS supplier_name, users.username AS created_by_name')
            ->select(new RawSql('CASE WHEN EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = inventory_movements.id) THEN 1 ELSE 0 END AS is_reversed'))
            ->join('warehouses', 'warehouses.id = inventory_movements.warehouse_id')
            ->join('suppliers', 'suppliers.id = inventory_movements.supplier_id', 'left')
            ->join('users', 'users.id = inventory_movements.created_by')
            ->find($id);
        if ($movement === null) {
            return redirect()->route('inventory-movements')->with('error', 'El movimiento no existe.');
        }

        $items = model(InventoryMovementItemModel::class)
            ->select('inventory_movement_items.*, materials.code AS material_code, materials.name AS material_name, measurement_units.symbol AS unit_symbol')
            ->select(new RawSql("CASE WHEN inventory_movement_items.direction = 1 THEN GREATEST(inventory_movement_items.quantity - COALESCE((SELECT SUM(v.quantity_basis) FROM inventory_valuation_events v WHERE v.movement_item_id = inventory_movement_items.id AND v.event_type = 'ALLOCATION'), 0), 0) ELSE CASE WHEN inventory_movement_items.pending_valuation THEN inventory_movement_items.quantity ELSE 0 END END AS remaining_valuation"))
            ->join('materials', 'materials.id = inventory_movement_items.material_id')
            ->join('measurement_units', 'measurement_units.id = materials.unit_id')
            ->where('movement_id', $id)
            ->orderBy('materials.name')
            ->findAll();

        $dispatch = db_connect()->table('inventory_dispatch_notes')->where('movement_id', $id)->get()->getRowArray();
        $dispatchItems = $dispatch === null ? [] : db_connect()->table('inventory_dispatch_items di')
            ->select('di.*, materials.code AS material_code, materials.name AS material_name, measurement_units.symbol AS unit_symbol')
            ->select(new RawSql('di.delivered_quantity + COALESCE((SELECT SUM(child.delivered_quantity) FROM inventory_dispatch_items child JOIN inventory_dispatch_notes child_note ON child_note.id = child.dispatch_note_id WHERE child.source_dispatch_item_id = di.id AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = child_note.movement_id)), 0) AS effective_delivered_quantity'))
            ->select(new RawSql('GREATEST(di.requested_quantity - di.delivered_quantity - COALESCE((SELECT SUM(child.delivered_quantity) FROM inventory_dispatch_items child JOIN inventory_dispatch_notes child_note ON child_note.id = child.dispatch_note_id WHERE child.source_dispatch_item_id = di.id AND NOT EXISTS (SELECT 1 FROM inventory_movements reversal WHERE reversal.original_movement_id = child_note.movement_id)), 0), 0) AS effective_pending_quantity'))
            ->join('materials', 'materials.id = di.material_id')
            ->join('measurement_units', 'measurement_units.id = materials.unit_id')
            ->where('di.dispatch_note_id', $dispatch['id'])
            ->orderBy('materials.name')
            ->get()->getResultArray();
        $sourceDispatch = $dispatch !== null && $dispatch['source_dispatch_note_id'] !== null
            ? db_connect()->table('inventory_dispatch_notes dn')->select('dn.id, dn.guide_number, mv.id AS movement_id, mv.movement_number')->join('inventory_movements mv', 'mv.id = dn.movement_id')->where('dn.id', $dispatch['source_dispatch_note_id'])->get()->getRowArray()
            : null;
        $followupDispatches = $dispatch === null ? [] : db_connect()->table('inventory_dispatch_notes dn')
            ->select('dn.id, dn.guide_number, dn.issue_date, mv.id AS movement_id, mv.movement_number')
            ->join('inventory_movements mv', 'mv.id = dn.movement_id')
            ->where('dn.source_dispatch_note_id', $dispatch['id'])
            ->orderBy('dn.id')
            ->get()->getResultArray();
        $attachments = db_connect()->table('inventory_attachments a')
            ->select('a.*, users.username AS uploaded_by_name')
            ->join('users', 'users.id = a.uploaded_by')
            ->where('a.movement_id', $id)
            ->orderBy('a.id', 'DESC')
            ->get()->getResultArray();

        return view('inventory/movements/show', [
            'movement'  => $movement,
            'items'     => $items,
            'dispatch'  => $dispatch,
            'dispatchItems' => $dispatchItems,
            'sourceDispatch' => $sourceDispatch,
            'followupDispatches' => $followupDispatches,
            'attachments' => $attachments,
            'showCosts' => auth()->user()?->can('financial.view') ?? false,
            'hasPendingValuation' => array_filter($items, static fn (array $item): bool => (float) $item['remaining_valuation'] > 0) !== [],
        ]);
    }

    public function stocks(): string
    {
        $warehouseId = (int) $this->request->getGet('warehouse_id');
        $builder = db_connect()->table('materials')
            ->select('materials.id, materials.code, materials.name, materials.minimum_stock, materials.allows_fraction, measurement_units.symbol AS unit_symbol, warehouses.id AS warehouse_id, warehouses.name AS warehouse_name')
            ->select(new RawSql('COALESCE(inventory_stocks.quantity, 0) AS quantity'))
            ->select(new RawSql('COALESCE(inventory_stocks.valued_quantity, 0) AS valued_quantity'))
            ->select('inventory_stocks.average_unit_cost, inventory_stocks.total_value')
            ->join('measurement_units', 'measurement_units.id = materials.unit_id')
            ->join('warehouses', 'warehouses.active = TRUE', 'inner', false)
            ->join('inventory_stocks', 'inventory_stocks.material_id = materials.id AND inventory_stocks.warehouse_id = warehouses.id', 'left')
            ->where('materials.active', true);
        if ($warehouseId > 0) {
            $builder->where('warehouses.id', $warehouseId);
        }

        return view('inventory/stocks/index', [
            'records'     => $builder->orderBy('warehouses.name')->orderBy('materials.name')->get()->getResultArray(),
            'warehouses'  => model(WarehouseModel::class)->where('active', true)->orderBy('name')->findAll(),
            'warehouseId' => $warehouseId,
            'showCosts'   => auth()->user()?->can('financial.view') ?? false,
        ]);
    }

    public function newEntry(): string
    {
        return view('inventory/movements/form', $this->formData('ENTRY'));
    }

    public function createEntry(): RedirectResponse
    {
        return $this->create('ENTRY');
    }

    public function newExit(): string
    {
        return view('inventory/movements/form', $this->formData('EXIT'));
    }

    public function createExit(): RedirectResponse
    {
        return $this->create('EXIT');
    }

    private function create(string $type): RedirectResponse
    {
        if (! $this->validateData($this->request->getPost(), $this->rules($type))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $service = new InventoryMovementService();
            $header = $this->headerPayload($type);
            $items = $this->itemsPayload($type === 'ENTRY');
            $movementId = $type === 'ENTRY'
                ? $service->createEntry($header, $items, (int) auth()->id())
                : $service->createExit($header, $items, (int) auth()->id());

            return redirect()->route('inventory-movement-show', [$movementId])
                ->with('message', $type === 'ENTRY' ? 'La entrada fue registrada.' : 'La salida fue registrada.');
        } catch (DomainException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible registrar el movimiento: {message}', ['message' => $exception->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'No fue posible registrar el movimiento. Intenta nuevamente.');
        }
    }

    /** @return array<string, mixed> */
    private function formData(string $type): array
    {
        return [
            'type'       => $type,
            'warehouses' => model(WarehouseModel::class)->where('active', true)->orderBy('name')->findAll(),
            'materials'  => model(MaterialModel::class)
                ->select('materials.*, measurement_units.symbol AS unit_symbol')
                ->join('measurement_units', 'measurement_units.id = materials.unit_id')
                ->where('materials.active', true)->orderBy('materials.name')->findAll(),
            'suppliers'  => $type === 'ENTRY'
                ? model(SupplierModel::class)->where('active', true)->orderBy('name')->findAll()
                : [],
        ];
    }

    /** @return array<string, array{label: string, rules: string}> */
    private function rules(string $type): array
    {
        $rules = [
            'warehouse_id'       => ['label' => 'Bodega', 'rules' => 'required|is_natural_no_zero'],
            'delivered_by_name'  => ['label' => 'Responsable que entrega', 'rules' => 'required|max_length[160]'],
            'received_by_name'   => ['label' => 'Responsable que recibe', 'rules' => 'required|max_length[160]'],
            'delivered_by_identification' => ['label' => 'Identificación de quien entrega', 'rules' => 'permit_empty|max_length[60]'],
            'delivered_by_position'       => ['label' => 'Cargo de quien entrega', 'rules' => 'permit_empty|max_length[120]'],
            'delivered_by_area_name'      => ['label' => 'Área de quien entrega', 'rules' => 'permit_empty|max_length[160]'],
            'received_by_identification'  => ['label' => 'Identificación de quien recibe', 'rules' => 'permit_empty|max_length[60]'],
            'received_by_position'        => ['label' => 'Cargo de quien recibe', 'rules' => 'permit_empty|max_length[120]'],
            'received_by_area_name'       => ['label' => 'Área de quien recibe', 'rules' => 'permit_empty|max_length[160]'],
            'document_number'    => ['label' => 'Documento', 'rules' => 'permit_empty|max_length[80]'],
            'document_date'      => ['label' => 'Fecha del documento', 'rules' => 'permit_empty|valid_date[Y-m-d]'],
            'observations'       => ['label' => 'Observaciones', 'rules' => 'permit_empty|max_length[2000]'],
        ];
        if ($type === 'ENTRY') {
            $rules['supplier_id'] = ['label' => 'Proveedor', 'rules' => 'permit_empty|is_natural_no_zero'];
            $rules['purchase_order_number'] = ['label' => 'Orden de compra', 'rules' => 'permit_empty|max_length[80]'];
        } else {
            $rules['reason'] = ['label' => 'Motivo', 'rules' => 'required|min_length[5]|max_length[1000]'];
            $rules['received_by_area_name'] = ['label' => 'Área receptora', 'rules' => 'required|max_length[160]'];
            $rules += [
                'authorization_number'    => ['label' => 'Autorización', 'rules' => 'permit_empty|max_length[100]'],
                'start_date'              => ['label' => 'Fecha de inicio', 'rules' => 'permit_empty|valid_date[Y-m-d]'],
                'end_date'                => ['label' => 'Fecha de fin', 'rules' => 'permit_empty|valid_date[Y-m-d]'],
                'issuer_name'             => ['label' => 'Emisor', 'rules' => 'permit_empty|max_length[180]'],
                'issuer_tax_identifier'   => ['label' => 'Identificación del emisor', 'rules' => 'permit_empty|max_length[30]'],
                'transporter_name'        => ['label' => 'Transportista', 'rules' => 'permit_empty|max_length[180]'],
                'transporter_identifier'  => ['label' => 'Identificación del transportista', 'rules' => 'permit_empty|max_length[30]'],
                'vehicle_plate'           => ['label' => 'Placa', 'rules' => 'permit_empty|max_length[20]'],
                'origin_place'            => ['label' => 'Partida', 'rules' => 'permit_empty|max_length[180]'],
                'destination_name'        => ['label' => 'Destinatario', 'rules' => 'permit_empty|max_length[180]'],
                'destination_identifier'  => ['label' => 'Identificación del destinatario', 'rules' => 'permit_empty|max_length[30]'],
                'destination_address'     => ['label' => 'Dirección de destino', 'rules' => 'permit_empty|max_length[300]'],
                'route_description'       => ['label' => 'Ruta', 'rules' => 'permit_empty|max_length[300]'],
                'related_document_number' => ['label' => 'Documento relacionado', 'rules' => 'permit_empty|max_length[100]'],
                'dispatch_description'    => ['label' => 'Descripción de la guía', 'rules' => 'permit_empty|max_length[2000]'],
            ];
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    private function headerPayload(string $type): array
    {
        return [
            'warehouse_id'                  => (int) $this->request->getPost('warehouse_id'),
            'supplier_id'                   => $type === 'ENTRY' ? $this->request->getPost('supplier_id') : null,
            'document_number'               => $this->request->getPost('document_number'),
            'purchase_order_number'         => $type === 'ENTRY' ? $this->request->getPost('purchase_order_number') : null,
            'document_date'                 => $this->request->getPost('document_date'),
            'reason'                        => $type === 'EXIT' ? $this->request->getPost('reason') : null,
            'observations'                  => $this->request->getPost('observations'),
            'delivered_by_name'             => trim((string) $this->request->getPost('delivered_by_name')),
            'delivered_by_identification'   => $this->request->getPost('delivered_by_identification'),
            'delivered_by_position'         => $this->request->getPost('delivered_by_position'),
            'delivered_by_area_name'        => $this->request->getPost('delivered_by_area_name'),
            'received_by_name'              => trim((string) $this->request->getPost('received_by_name')),
            'received_by_identification'    => $this->request->getPost('received_by_identification'),
            'received_by_position'          => $this->request->getPost('received_by_position'),
            'received_by_area_name'         => $this->request->getPost('received_by_area_name'),
            'authorization_number'          => $type === 'EXIT' ? $this->request->getPost('authorization_number') : null,
            'start_date'                    => $type === 'EXIT' ? $this->request->getPost('start_date') : null,
            'end_date'                      => $type === 'EXIT' ? $this->request->getPost('end_date') : null,
            'issuer_name'                   => $type === 'EXIT' ? $this->request->getPost('issuer_name') : null,
            'issuer_tax_identifier'         => $type === 'EXIT' ? $this->request->getPost('issuer_tax_identifier') : null,
            'transporter_name'              => $type === 'EXIT' ? $this->request->getPost('transporter_name') : null,
            'transporter_identifier'        => $type === 'EXIT' ? $this->request->getPost('transporter_identifier') : null,
            'vehicle_plate'                 => $type === 'EXIT' ? $this->request->getPost('vehicle_plate') : null,
            'origin_place'                  => $type === 'EXIT' ? $this->request->getPost('origin_place') : null,
            'destination_name'              => $type === 'EXIT' ? $this->request->getPost('destination_name') : null,
            'destination_identifier'        => $type === 'EXIT' ? $this->request->getPost('destination_identifier') : null,
            'destination_address'           => $type === 'EXIT' ? $this->request->getPost('destination_address') : null,
            'route_description'             => $type === 'EXIT' ? $this->request->getPost('route_description') : null,
            'related_document_number'       => $type === 'EXIT' ? $this->request->getPost('related_document_number') : null,
            'dispatch_description'          => $type === 'EXIT' ? $this->request->getPost('dispatch_description') : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function itemsPayload(bool $withCost): array
    {
        $materialIds = (array) $this->request->getPost('material_id');
        $quantities = (array) $this->request->getPost('quantity');
        $requestedQuantities = (array) $this->request->getPost('requested_quantity');
        $costs = (array) $this->request->getPost('unit_cost');
        $reasons = (array) $this->request->getPost('no_cost_reason');
        $items = [];
        foreach ($materialIds as $index => $materialId) {
            if ((string) $materialId === '') {
                continue;
            }
            $items[] = [
                'material_id'   => $materialId,
                'quantity'      => $quantities[$index] ?? '',
                'requested_quantity' => $withCost ? null : ($requestedQuantities[$index] ?? ''),
                'unit_cost'     => $withCost ? ($costs[$index] ?? null) : null,
                'no_cost_reason'=> $withCost ? ($reasons[$index] ?? null) : null,
            ];
        }

        return $items;
    }
}
