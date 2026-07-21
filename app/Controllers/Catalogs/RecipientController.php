<?php

declare(strict_types=1);

namespace App\Controllers\Catalogs;

use App\Controllers\BaseController;
use App\Models\RecipientModel;
use App\Services\CatalogStateService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;

final class RecipientController extends BaseController
{
    public function index(): string
    {
        $search = trim((string) $this->request->getGet('q'));
        $model = model(RecipientModel::class);
        if ($search !== '') {
            $model->groupStart()->like('name', $search)->orLike('document_number', $search)->groupEnd();
        }

        return view('catalogs/recipients/index', [
            'records' => $model->orderBy('name')->paginate(20),
            'pager' => $model->pager,
            'search' => $search,
        ]);
    }

    public function new(): string
    {
        return view('catalogs/recipients/form', ['record' => null]);
    }

    public function create(): RedirectResponse
    {
        return $this->persist(null);
    }

    public function edit(int $id)
    {
        $record = model(RecipientModel::class)->find($id);

        return $record === null
            ? redirect()->route('recipients')->with('error', 'El destinatario no existe.')
            : view('catalogs/recipients/form', ['record' => $record]);
    }

    public function update(int $id): RedirectResponse
    {
        if (model(RecipientModel::class)->find($id) === null) {
            return redirect()->route('recipients')->with('error', 'El destinatario no existe.');
        }

        return $this->persist($id);
    }

    public function toggle(int $id): RedirectResponse
    {
        try {
            $active = (new CatalogStateService())->toggle('recipients', $id, (int) auth()->id());
        } catch (DomainException $exception) {
            return redirect()->route('recipients')->with('error', $exception->getMessage());
        }

        return redirect()->route('recipients')->with('message', $active ? 'El destinatario fue activado.' : 'El destinatario fue desactivado.');
    }

    private function persist(?int $id): RedirectResponse
    {
        $rules = [
            'name' => ['label' => 'Nombre o razón social', 'rules' => 'required|max_length[180]'],
            'document_type' => ['label' => 'Tipo de documento', 'rules' => 'permit_empty|max_length[30]'],
            'document_number' => ['label' => 'Documento', 'rules' => 'permit_empty|max_length[50]'],
            'address' => ['label' => 'Dirección', 'rules' => 'required|max_length[300]'],
            'route' => ['label' => 'Ruta', 'rules' => 'permit_empty|max_length[300]'],
            'contact_name' => ['label' => 'Contacto', 'rules' => 'permit_empty|max_length[120]'],
            'email' => ['label' => 'Correo', 'rules' => 'permit_empty|max_length[254]|valid_email'],
            'phone' => ['label' => 'Teléfono', 'rules' => 'permit_empty|max_length[40]'],
        ];
        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $type = trim((string) $this->request->getPost('document_type'));
        $number = trim((string) $this->request->getPost('document_number'));
        if (($type === '') !== ($number === '')) {
            return redirect()->back()->withInput()->with('error', 'El tipo y el número de documento deben completarse juntos.');
        }
        if ($type !== '') {
            $duplicate = model(RecipientModel::class)->where('document_type', $type)->where('document_number', $number);
            if ($id !== null) {
                $duplicate->where('id !=', $id);
            }
            if ($duplicate->countAllResults() > 0) {
                return redirect()->back()->withInput()->with('error', 'Ya existe un destinatario con ese documento.');
            }
        }

        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'document_type' => $type ?: null,
            'document_number' => $number ?: null,
            'address' => trim((string) $this->request->getPost('address')),
            'route' => trim((string) $this->request->getPost('route')) ?: null,
            'contact_name' => trim((string) $this->request->getPost('contact_name')) ?: null,
            'email' => strtolower(trim((string) $this->request->getPost('email'))) ?: null,
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'updated_by' => (int) auth()->id(),
        ];
        if ($id === null) {
            $data += ['active' => true, 'created_by' => (int) auth()->id()];
            model(RecipientModel::class)->insert($data);
        } else {
            model(RecipientModel::class)->update($id, $data);
        }

        return redirect()->route('recipients')->with('message', $id === null ? 'El destinatario fue creado.' : 'El destinatario fue actualizado.');
    }
}
