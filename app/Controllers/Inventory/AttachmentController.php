<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use DomainException;
use Throwable;

final class AttachmentController extends BaseController
{
    public function upload(int $movementId): RedirectResponse
    {
        try {
            $file = $this->request->getFile('attachment');
            if ($file === null) {
                throw new DomainException('Selecciona un archivo.');
            }
            Services::attachmentFiles()->uploadForMovement($movementId, $file, (int) auth()->id());

            return redirect()->route('inventory-movement-show', [$movementId])->with('message', 'El respaldo fue cargado de forma privada.');
        } catch (DomainException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible cargar el respaldo: {message}', ['message' => $exception->getMessage()]);

            return redirect()->back()->with('error', 'No fue posible almacenar el archivo.');
        }
    }

    public function download(int $attachmentId): ResponseInterface
    {
        try {
            $download = Services::attachmentFiles()->download($attachmentId);
            $record = $download['record'];
            $safeName = 'documento-' . $record['id'] . '.' . $record['extension'];

            return $this->response
                ->setHeader('Content-Type', (string) $record['mime_type'])
                ->setHeader('Content-Length', (string) strlen($download['contents']))
                ->setHeader('Content-Disposition', 'attachment; filename="' . $safeName . '"')
                ->setBody($download['contents']);
        } catch (DomainException $exception) {
            return $this->response->setStatusCode(404)->setBody('Archivo no disponible.');
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible descargar el respaldo: {message}', ['message' => $exception->getMessage()]);

            return $this->response->setStatusCode(500)->setBody('No fue posible descargar el archivo.');
        }
    }

    public function archive(int $attachmentId): RedirectResponse
    {
        try {
            Services::attachmentFiles()->archive(
                $attachmentId,
                (string) $this->request->getPost('reason'),
                (int) auth()->id(),
            );

            return redirect()->back()->with('message', 'El archivo fue archivado lógicamente.');
        } catch (DomainException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible archivar el respaldo: {message}', ['message' => $exception->getMessage()]);

            return redirect()->back()->with('error', 'No fue posible archivar el archivo.');
        }
    }
}
