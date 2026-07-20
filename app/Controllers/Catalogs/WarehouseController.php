<?php

declare(strict_types=1);

namespace App\Controllers\Catalogs;

use App\Controllers\BaseController;
use App\Models\WarehouseModel;
use App\Services\CatalogStateService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;

final class WarehouseController extends BaseController
{
    public function index(): string
    {
        $search = trim((string) $this->request->getGet('q'));
        $model = model(WarehouseModel::class);
        if ($search !== '') {
            $model->groupStart()->like('code', $search)->orLike('name', $search)->groupEnd();
        }

        return view('catalogs/warehouses/index', [
            'records' => $model->orderBy('is_main', 'DESC')->orderBy('name')->paginate(20),
            'pager'   => $model->pager,
            'search'  => $search,
        ]);
    }

    public function new(): string
    {
        return view('catalogs/warehouses/form', ['record' => null]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validateData($this->request->getPost(), $this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        model(WarehouseModel::class)->insert($this->payload(true));

        return redirect()->route('warehouses')->with('message', 'La bodega fue creada.');
    }

    public function edit(int $id)
    {
        $record = model(WarehouseModel::class)->find($id);
        if ($record === null) {
            return redirect()->route('warehouses')->with('error', 'La bodega no existe.');
        }

        return view('catalogs/warehouses/form', ['record' => $record]);
    }

    public function update(int $id): RedirectResponse
    {
        $model = model(WarehouseModel::class);
        $record = $model->find($id);
        if ($record === null) {
            return redirect()->route('warehouses')->with('error', 'La bodega no existe.');
        }

        if (! $this->validateData($this->request->getPost(), $this->rules($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model->update($id, $this->payload(false));

        return redirect()->route('warehouses')->with('message', 'La bodega fue actualizada.');
    }

    public function toggle(int $id): RedirectResponse
    {
        try {
            $active = (new CatalogStateService())->toggle('warehouses', $id, (int) auth()->id());
        } catch (DomainException $exception) {
            return redirect()->route('warehouses')->with('error', $exception->getMessage());
        }

        return redirect()->route('warehouses')->with('message', $active ? 'La bodega fue activada.' : 'La bodega fue desactivada.');
    }

    private function rules(?int $id = null): array
    {
        $unique = $id === null ? 'is_unique[warehouses.code]' : "is_unique[warehouses.code,id,{$id}]";

        return [
            'code'        => ['label' => 'Código', 'rules' => "required|max_length[30]|regex_match[/\\A[A-Za-z0-9.-]+\\z/]|{$unique}"],
            'name'        => ['label' => 'Nombre', 'rules' => 'required|max_length[120]'],
            'description' => ['label' => 'Descripción', 'rules' => 'permit_empty|max_length[1000]'],
        ];
    }

    private function payload(bool $creating): array
    {
        $data = [
            'code'        => strtoupper(trim((string) $this->request->getPost('code'))),
            'name'        => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'updated_by'  => (int) auth()->id(),
        ];
        if ($creating) {
            $data += ['is_main' => false, 'active' => true, 'created_by' => (int) auth()->id()];
        }

        return $data;
    }
}
