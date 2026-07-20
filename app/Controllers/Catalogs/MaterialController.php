<?php

declare(strict_types=1);

namespace App\Controllers\Catalogs;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\MaterialModel;
use App\Models\MeasurementUnitModel;
use App\Services\CatalogStateService;
use App\Services\QuantityService;
use CodeIgniter\Database\RawSql;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;

final class MaterialController extends BaseController
{
    public function index(): string
    {
        $search = trim((string) $this->request->getGet('q'));
        $model = model(MaterialModel::class)
            ->select('materials.*, categories.name AS category_name, measurement_units.name AS unit_name, measurement_units.symbol AS unit_symbol')
            ->select(new RawSql('COALESCE((SELECT SUM(quantity) FROM inventory_stocks WHERE inventory_stocks.material_id = materials.id), 0) AS current_stock'))
            ->join('categories', 'categories.id = materials.category_id')
            ->join('measurement_units', 'measurement_units.id = materials.unit_id');

        if ($search !== '') {
            $model->groupStart()->like('materials.code', $search)->orLike('materials.name', $search)->groupEnd();
        }

        return view('catalogs/materials/index', [
            'records' => $model->orderBy('materials.name')->paginate(20),
            'pager'   => $model->pager,
            'search'  => $search,
        ]);
    }

    public function new(): string
    {
        return view('catalogs/materials/form', $this->formData());
    }

    public function create(): RedirectResponse
    {
        if (! $this->validateData($this->request->getPost(), $this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        if ($response = $this->validateRelationsAndQuantity()) {
            return $response;
        }

        model(MaterialModel::class)->insert($this->payload(true));

        return redirect()->route('materials')->with('message', 'El material fue creado.');
    }

    public function edit(int $id)
    {
        $record = model(MaterialModel::class)->find($id);
        if ($record === null) {
            return redirect()->route('materials')->with('error', 'El material no existe.');
        }

        return view('catalogs/materials/form', $this->formData($record));
    }

    public function update(int $id): RedirectResponse
    {
        $model = model(MaterialModel::class);
        if ($model->find($id) === null) {
            return redirect()->route('materials')->with('error', 'El material no existe.');
        }
        if (! $this->validateData($this->request->getPost(), $this->rules($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        if ($response = $this->validateRelationsAndQuantity()) {
            return $response;
        }

        $model->update($id, $this->payload(false));

        return redirect()->route('materials')->with('message', 'El material fue actualizado.');
    }

    public function toggle(int $id): RedirectResponse
    {
        try {
            $active = (new CatalogStateService())->toggle('materials', $id, (int) auth()->id());
        } catch (DomainException $exception) {
            return redirect()->route('materials')->with('error', $exception->getMessage());
        }

        return redirect()->route('materials')->with('message', $active ? 'El material fue activado.' : 'El material fue desactivado.');
    }

    private function rules(?int $id = null): array
    {
        $unique = $id === null ? 'is_unique[materials.code]' : "is_unique[materials.code,id,{$id}]";

        return [
            'code'          => ['label' => 'Código', 'rules' => "required|max_length[40]|regex_match[/\\A[A-Za-z0-9.-]+\\z/]|{$unique}"],
            'name'          => ['label' => 'Nombre', 'rules' => 'required|max_length[160]'],
            'category_id'   => ['label' => 'Categoría', 'rules' => 'required|is_natural_no_zero'],
            'unit_id'       => ['label' => 'Unidad', 'rules' => 'required|is_natural_no_zero'],
            'minimum_stock' => ['label' => 'Stock mínimo', 'rules' => 'required|decimal|max_length[15]'],
            'description'   => ['label' => 'Descripción', 'rules' => 'permit_empty|max_length[1000]'],
        ];
    }

    private function validateRelationsAndQuantity(): ?RedirectResponse
    {
        $category = model(CategoryModel::class)->find((int) $this->request->getPost('category_id'));
        $unit = model(MeasurementUnitModel::class)->find((int) $this->request->getPost('unit_id'));
        if ($category === null || ! (bool) $category['active'] || $unit === null || ! (bool) $unit['active']) {
            return redirect()->back()->withInput()->with('error', 'La categoría o unidad seleccionada no está disponible.');
        }

        $allowsFraction = $this->request->getPost('allows_fraction') === '1';
        $minimumStock = trim((string) $this->request->getPost('minimum_stock'));
        if (! (new QuantityService())->isValid($minimumStock, $allowsFraction)) {
            return redirect()->back()->withInput()->with('error', $allowsFraction
                ? 'El stock mínimo admite hasta tres decimales.'
                : 'Este material no admite cantidades fraccionarias.');
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $record
     * @return array<string, mixed>
     */
    private function formData(?array $record = null): array
    {
        return [
            'record'     => $record,
            'categories' => model(CategoryModel::class)->where('active', true)->orderBy('name')->findAll(),
            'units'      => model(MeasurementUnitModel::class)->where('active', true)->orderBy('name')->findAll(),
        ];
    }

    private function payload(bool $creating): array
    {
        $data = [
            'code'            => strtoupper(trim((string) $this->request->getPost('code'))),
            'name'            => trim((string) $this->request->getPost('name')),
            'category_id'     => (int) $this->request->getPost('category_id'),
            'unit_id'         => (int) $this->request->getPost('unit_id'),
            'description'     => trim((string) $this->request->getPost('description')) ?: null,
            'allows_fraction' => $this->request->getPost('allows_fraction') === '1',
            'minimum_stock'   => trim((string) $this->request->getPost('minimum_stock')),
            'updated_by'      => (int) auth()->id(),
        ];
        if ($creating) {
            $data += ['active' => true, 'created_by' => (int) auth()->id()];
        }

        return $data;
    }
}
