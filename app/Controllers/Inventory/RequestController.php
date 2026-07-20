<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Models\InventoryRequestModel;
use App\Models\MaterialModel;
use App\Models\WarehouseModel;
use App\Services\InventoryMovementService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

final class RequestController extends BaseController
{
    public function index(): string
    {
        $status = strtoupper(trim((string) $this->request->getGet('status')));
        $model = model(InventoryRequestModel::class)
            ->select('inventory_requests.*, warehouses.name AS warehouse_name')
            ->join('warehouses', 'warehouses.id = inventory_requests.warehouse_id');
        if (in_array($status, ['PENDING', 'REJECTED', 'EXECUTED'], true)) {
            $model->where('inventory_requests.status', $status);
        }

        return view('inventory/requests/index', [
            'records' => $model->orderBy('inventory_requests.id', 'DESC')->paginate(25),
            'pager'   => $model->pager,
            'status'  => $status,
        ]);
    }

    public function newAdjustment(): string
    {
        return view('inventory/requests/form', [
            'warehouses' => model(WarehouseModel::class)->where('active', true)->orderBy('name')->findAll(),
            'materials'  => model(MaterialModel::class)
                ->select('materials.*, measurement_units.symbol AS unit_symbol')
                ->join('measurement_units', 'measurement_units.id = materials.unit_id')
                ->where('materials.active', true)->orderBy('materials.name')->findAll(),
        ]);
    }

    public function createAdjustment(): RedirectResponse
    {
        if (! $this->validateData($this->request->getPost(), [
            'warehouse_id' => ['label' => 'Bodega', 'rules' => 'required|is_natural_no_zero'],
            'reason'       => ['label' => 'Motivo', 'rules' => 'required|min_length[5]|max_length[2000]'],
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $materialIds = (array) $this->request->getPost('material_id');
        $deltas = (array) $this->request->getPost('quantity_delta');
        $costs = (array) $this->request->getPost('unit_cost');
        $reasons = (array) $this->request->getPost('no_cost_reason');
        $items = [];
        foreach ($materialIds as $index => $materialId) {
            if ((string) $materialId !== '') {
                $items[] = [
                    'material_id'    => $materialId,
                    'quantity_delta' => $deltas[$index] ?? '',
                    'unit_cost'      => $costs[$index] ?? null,
                    'no_cost_reason' => $reasons[$index] ?? null,
                ];
            }
        }

        try {
            (new InventoryMovementService())->requestAdjustment(
                (int) $this->request->getPost('warehouse_id'),
                (string) $this->request->getPost('reason'),
                $items,
                (int) auth()->id(),
            );

            return redirect()->route('inventory-requests')->with('message', 'La solicitud de ajuste fue creada.');
        } catch (DomainException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible solicitar el ajuste: {message}', ['message' => $exception->getMessage()]);

            return redirect()->back()->withInput()->with('error', 'No fue posible crear la solicitud.');
        }
    }

    public function createReversal(int $movementId): RedirectResponse
    {
        $reason = trim((string) $this->request->getPost('reason'));
        try {
            (new InventoryMovementService())->requestReversal($movementId, $reason, (int) auth()->id());

            return redirect()->route('inventory-requests')->with('message', 'La solicitud de reversión fue creada.');
        } catch (DomainException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible solicitar la reversión: {message}', ['message' => $exception->getMessage()]);

            return redirect()->back()->with('error', 'No fue posible crear la solicitud.');
        }
    }

    public function decide(int $requestId): RedirectResponse
    {
        $request = model(InventoryRequestModel::class)->find($requestId);
        if ($request === null) {
            return redirect()->route('inventory-requests')->with('error', 'La solicitud no existe.');
        }
        $permission = $request['type'] === 'REVERSAL'
            ? 'inventory.reversals.approve'
            : 'inventory.adjustments.approve';
        if (! auth()->user()?->can($permission)) {
            return redirect()->route('inventory-requests')->with('error', 'No tienes permiso para decidir esta solicitud.');
        }

        $decision = (string) $this->request->getPost('decision');
        if (! in_array($decision, ['approve', 'reject'], true)) {
            return redirect()->back()->with('error', 'La decisión no es válida.');
        }

        try {
            $movementId = (new InventoryMovementService())->decideRequest(
                $requestId,
                $decision === 'approve',
                (string) $this->request->getPost('comment'),
                (int) auth()->id(),
            );

            return $movementId === null
                ? redirect()->route('inventory-requests')->with('message', 'La solicitud fue rechazada.')
                : redirect()->route('inventory-movement-show', [$movementId])->with('message', 'La solicitud fue aprobada y ejecutada.');
        } catch (DomainException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible decidir la solicitud: {message}', ['message' => $exception->getMessage()]);

            return redirect()->back()->with('error', 'No fue posible procesar la solicitud.');
        }
    }
}
