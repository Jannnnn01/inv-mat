<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Models\MaterialModel;
use App\Models\RecipientModel;
use App\Models\WarehouseModel;
use App\Services\DispatchRequestService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

final class DispatchRequestController extends BaseController
{
    public function index(): string
    {
        $builder = db_connect()->table('dispatch_requests r')->select('r.*, w.name AS warehouse_name, d.name AS recipient_name')
            ->join('warehouses w', 'w.id = r.warehouse_id')->join('recipients d', 'd.id = r.recipient_id')
            ->orderBy('r.id', 'DESC');
        if (! (auth()->user()?->can('inventory.dispatch_requests.approve') ?? false)
            && ! (auth()->user()?->can('inventory.exits.create') ?? false)) {
            $builder->where('r.requested_by', (int) auth()->id());
        }
        $records = $builder->get()->getResultArray();

        return view('inventory/dispatch_requests/index', ['records' => $records]);
    }

    public function new(): string
    {
        return view('inventory/dispatch_requests/form', [
            'warehouses' => model(WarehouseModel::class)->where('active', true)->orderBy('name')->findAll(),
            'recipients' => model(RecipientModel::class)->where('active', true)->orderBy('name')->findAll(),
            'materials' => model(MaterialModel::class)->select('materials.*, measurement_units.symbol AS unit_symbol')->join('measurement_units', 'measurement_units.id = materials.unit_id')->where('materials.active', true)->orderBy('materials.name')->findAll(),
        ]);
    }

    public function show(int $id): string|RedirectResponse
    {
        $record = db_connect()->table('dispatch_requests r')
            ->select('r.*, w.name AS warehouse_name, d.name AS recipient_name, d.document_number AS recipient_document, d.address AS recipient_address, d.route AS recipient_route')
            ->join('warehouses w', 'w.id = r.warehouse_id')
            ->join('recipients d', 'd.id = r.recipient_id')
            ->where('r.id', $id)->get()->getRowArray();
        if ($record === null) {
            return redirect()->route('dispatch-requests')->with('error', 'La requisición no existe.');
        }
        $canReviewAll = (auth()->user()?->can('inventory.dispatch_requests.approve') ?? false)
            || (auth()->user()?->can('inventory.exits.create') ?? false);
        if (! $canReviewAll && (int) $record['requested_by'] !== (int) auth()->id()) {
            return redirect()->route('dispatch-requests')->with('error', 'No tienes acceso a esa requisición.');
        }

        $items = db_connect()->table('dispatch_request_items i')
            ->select('i.*, m.code, m.name AS material_name, u.symbol AS unit_symbol')
            ->join('materials m', 'm.id = i.material_id')
            ->join('measurement_units u', 'u.id = m.unit_id')
            ->where('i.request_id', $id)->orderBy('m.name')->get()->getResultArray();

        return view('inventory/dispatch_requests/show', ['record' => $record, 'items' => $items]);
    }

    public function create(): RedirectResponse
    {
        $rules = [
            'warehouse_id' => ['label' => 'Bodega', 'rules' => 'required|is_natural_no_zero'],
            'recipient_id' => ['label' => 'Destinatario', 'rules' => 'required|is_natural_no_zero'],
            'reason' => ['label' => 'Motivo', 'rules' => 'required|min_length[5]|max_length[2000]'],
            'observations' => ['label' => 'Observaciones', 'rules' => 'permit_empty|max_length[2000]'],
        ];
        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $items = [];
        foreach ((array) $this->request->getPost('material_id') as $index => $materialId) {
            $items[] = ['material_id' => $materialId, 'quantity' => ((array) $this->request->getPost('quantity'))[$index] ?? null];
        }
        try {
            (new DispatchRequestService())->create((int) $this->request->getPost('warehouse_id'), (int) $this->request->getPost('recipient_id'), (string) $this->request->getPost('reason'), $this->request->getPost('observations'), $items, (int) auth()->id());
        } catch (DomainException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Fallo al crear requisición: {message}', ['message' => $exception->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'No fue posible crear la requisición.');
        }

        return redirect()->route('dispatch-requests')->with('message', 'La requisición fue enviada para aprobación.');
    }

    public function decide(int $id): RedirectResponse
    {
        $decision = (string) $this->request->getPost('decision');
        if (! in_array($decision, ['approve', 'reject'], true)) {
            return redirect()->route('dispatch-requests')->with('error', 'La decisión no es válida.');
        }
        try {
            (new DispatchRequestService())->decide($id, $decision === 'approve', (string) $this->request->getPost('comment'), (int) auth()->id());
        } catch (DomainException $exception) {
            return redirect()->route('dispatch-request-show', [$id])->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Fallo al decidir requisición: {message}', ['message' => $exception->getMessage()]);
            return redirect()->route('dispatch-request-show', [$id])->with('error', 'No fue posible procesar la requisición.');
        }

        return redirect()->route('dispatch-request-show', [$id])->with('message', $decision === 'approve' ? 'La requisición fue aprobada y su stock quedó reservado.' : 'La requisición fue rechazada.');
    }
}
