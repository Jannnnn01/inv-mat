<?php

declare(strict_types=1);

namespace App\Controllers\Catalogs;

use App\Controllers\BaseController;
use App\Models\SupplierModel;
use App\Services\CatalogStateService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;

final class SupplierController extends BaseController
{
    public function index(): string
    {
        $search = trim((string) $this->request->getGet('q'));
        $model = model(SupplierModel::class);
        if ($search !== '') {
            $model->groupStart()->like('name', $search)->orLike('document_number', $search)->groupEnd();
        }

        return view('catalogs/suppliers/index', [
            'records' => $model->orderBy('name')->paginate(20),
            'pager'   => $model->pager,
            'search'  => $search,
        ]);
    }

    public function new(): string
    {
        return view('catalogs/suppliers/form', ['record' => null]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validateData($this->request->getPost(), $this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        if ($response = $this->validateDocumentPair()) {
            return $response;
        }

        model(SupplierModel::class)->insert($this->payload(true));

        return redirect()->route('suppliers')->with('message', 'El proveedor fue creado.');
    }

    public function edit(int $id)
    {
        $record = model(SupplierModel::class)->find($id);

        return $record === null
            ? redirect()->route('suppliers')->with('error', 'El proveedor no existe.')
            : view('catalogs/suppliers/form', ['record' => $record]);
    }

    public function update(int $id): RedirectResponse
    {
        $model = model(SupplierModel::class);
        if ($model->find($id) === null) {
            return redirect()->route('suppliers')->with('error', 'El proveedor no existe.');
        }
        if (! $this->validateData($this->request->getPost(), $this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        if ($response = $this->validateDocumentPair($id)) {
            return $response;
        }

        $model->update($id, $this->payload(false));

        return redirect()->route('suppliers')->with('message', 'El proveedor fue actualizado.');
    }

    public function toggle(int $id): RedirectResponse
    {
        try {
            $active = (new CatalogStateService())->toggle('suppliers', $id, (int) auth()->id());
        } catch (DomainException $exception) {
            return redirect()->route('suppliers')->with('error', $exception->getMessage());
        }

        return redirect()->route('suppliers')->with('message', $active ? 'El proveedor fue activado.' : 'El proveedor fue desactivado.');
    }

    private function rules(): array
    {
        return [
            'name'            => ['label' => 'Nombre', 'rules' => 'required|max_length[160]'],
            'document_type'   => ['label' => 'Tipo de documento', 'rules' => 'permit_empty|max_length[30]'],
            'document_number' => ['label' => 'Número de documento', 'rules' => 'permit_empty|max_length[50]'],
            'contact_name'    => ['label' => 'Contacto', 'rules' => 'permit_empty|max_length[120]'],
            'email'           => ['label' => 'Correo', 'rules' => 'permit_empty|max_length[254]|valid_email'],
            'phone'           => ['label' => 'Teléfono', 'rules' => 'permit_empty|max_length[40]'],
            'address'         => ['label' => 'Dirección', 'rules' => 'permit_empty|max_length[255]'],
        ];
    }

    private function validateDocumentPair(?int $id = null): ?RedirectResponse
    {
        $type = trim((string) $this->request->getPost('document_type'));
        $number = trim((string) $this->request->getPost('document_number'));
        if (($type === '') !== ($number === '')) {
            return redirect()->back()->withInput()->with('error', 'El tipo y el número de documento deben completarse juntos.');
        }

        if ($type !== '') {
            $model = model(SupplierModel::class)->where('document_type', $type)->where('document_number', $number);
            if ($id !== null) {
                $model->where('id !=', $id);
            }
            if ($model->countAllResults() > 0) {
                return redirect()->back()->withInput()->with('error', 'Ya existe un proveedor con ese documento.');
            }
        }

        return null;
    }

    private function payload(bool $creating): array
    {
        $data = [
            'name'            => trim((string) $this->request->getPost('name')),
            'document_type'   => trim((string) $this->request->getPost('document_type')) ?: null,
            'document_number' => trim((string) $this->request->getPost('document_number')) ?: null,
            'contact_name'    => trim((string) $this->request->getPost('contact_name')) ?: null,
            'email'           => strtolower(trim((string) $this->request->getPost('email'))) ?: null,
            'phone'           => trim((string) $this->request->getPost('phone')) ?: null,
            'address'         => trim((string) $this->request->getPost('address')) ?: null,
            'updated_by'      => (int) auth()->id(),
        ];
        if ($creating) {
            $data += ['active' => true, 'created_by' => (int) auth()->id()];
        }

        return $data;
    }
}
