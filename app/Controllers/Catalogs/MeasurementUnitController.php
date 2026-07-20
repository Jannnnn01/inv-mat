<?php

declare(strict_types=1);

namespace App\Controllers\Catalogs;

use App\Controllers\BaseController;
use App\Models\MeasurementUnitModel;
use App\Services\CatalogStateService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;

final class MeasurementUnitController extends BaseController
{
    public function index(): string
    {
        $model = model(MeasurementUnitModel::class);

        return view('catalogs/units/index', [
            'records' => $model->orderBy('name')->paginate(20),
            'pager'   => $model->pager,
        ]);
    }

    public function new(): string
    {
        return view('catalogs/units/form', ['record' => null]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validateData($this->request->getPost(), $this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        model(MeasurementUnitModel::class)->insert($this->payload(true));

        return redirect()->route('units')->with('message', 'La unidad de medida fue creada.');
    }

    public function edit(int $id)
    {
        $record = model(MeasurementUnitModel::class)->find($id);

        return $record === null
            ? redirect()->route('units')->with('error', 'La unidad no existe.')
            : view('catalogs/units/form', ['record' => $record]);
    }

    public function update(int $id): RedirectResponse
    {
        $model = model(MeasurementUnitModel::class);
        if ($model->find($id) === null) {
            return redirect()->route('units')->with('error', 'La unidad no existe.');
        }
        if (! $this->validateData($this->request->getPost(), $this->rules($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model->update($id, $this->payload(false));

        return redirect()->route('units')->with('message', 'La unidad de medida fue actualizada.');
    }

    public function toggle(int $id): RedirectResponse
    {
        try {
            $active = (new CatalogStateService())->toggle('measurement_units', $id, (int) auth()->id());
        } catch (DomainException $exception) {
            return redirect()->route('units')->with('error', $exception->getMessage());
        }

        return redirect()->route('units')->with('message', $active ? 'La unidad fue activada.' : 'La unidad fue desactivada.');
    }

    private function rules(?int $id = null): array
    {
        $unique = $id === null ? 'is_unique[measurement_units.code]' : "is_unique[measurement_units.code,id,{$id}]";

        return [
            'code'   => ['label' => 'Código', 'rules' => "required|max_length[20]|regex_match[/\\A[A-Za-z0-9.-]+\\z/]|{$unique}"],
            'name'   => ['label' => 'Nombre', 'rules' => 'required|max_length[80]'],
            'symbol' => ['label' => 'Símbolo', 'rules' => 'required|max_length[20]'],
        ];
    }

    private function payload(bool $creating): array
    {
        $data = [
            'code'       => strtoupper(trim((string) $this->request->getPost('code'))),
            'name'       => trim((string) $this->request->getPost('name')),
            'symbol'     => trim((string) $this->request->getPost('symbol')),
            'updated_by' => (int) auth()->id(),
        ];
        if ($creating) {
            $data += ['active' => true, 'created_by' => (int) auth()->id()];
        }

        return $data;
    }
}
