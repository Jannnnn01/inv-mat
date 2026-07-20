<?php

declare(strict_types=1);

namespace App\Controllers\Catalogs;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Services\CatalogStateService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;

final class CategoryController extends BaseController
{
    public function index(): string
    {
        $search = trim((string) $this->request->getGet('q'));
        $model = model(CategoryModel::class);
        if ($search !== '') {
            $model->groupStart()->like('code', $search)->orLike('name', $search)->groupEnd();
        }

        return view('catalogs/categories/index', [
            'records' => $model->orderBy('name')->paginate(20),
            'pager'   => $model->pager,
            'search'  => $search,
        ]);
    }

    public function new(): string
    {
        return view('catalogs/categories/form', ['record' => null]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validateData($this->request->getPost(), $this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        model(CategoryModel::class)->insert($this->payload(true));

        return redirect()->route('categories')->with('message', 'La categoría fue creada.');
    }

    public function edit(int $id)
    {
        $record = model(CategoryModel::class)->find($id);

        return $record === null
            ? redirect()->route('categories')->with('error', 'La categoría no existe.')
            : view('catalogs/categories/form', ['record' => $record]);
    }

    public function update(int $id): RedirectResponse
    {
        $model = model(CategoryModel::class);
        if ($model->find($id) === null) {
            return redirect()->route('categories')->with('error', 'La categoría no existe.');
        }
        if (! $this->validateData($this->request->getPost(), $this->rules($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model->update($id, $this->payload(false));

        return redirect()->route('categories')->with('message', 'La categoría fue actualizada.');
    }

    public function toggle(int $id): RedirectResponse
    {
        try {
            $active = (new CatalogStateService())->toggle('categories', $id, (int) auth()->id());
        } catch (DomainException $exception) {
            return redirect()->route('categories')->with('error', $exception->getMessage());
        }

        return redirect()->route('categories')->with('message', $active ? 'La categoría fue activada.' : 'La categoría fue desactivada.');
    }

    private function rules(?int $id = null): array
    {
        $unique = $id === null ? 'is_unique[categories.code]' : "is_unique[categories.code,id,{$id}]";

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
            $data += ['active' => true, 'created_by' => (int) auth()->id()];
        }

        return $data;
    }
}
