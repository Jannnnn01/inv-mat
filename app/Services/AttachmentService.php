<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AttachmentStorageInterface;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Storage;
use DomainException;
use finfo;
use RuntimeException;
use Throwable;

final class AttachmentService
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TYPES = [
        'application/pdf' => ['pdf'],
        'image/jpeg'      => ['jpg', 'jpeg'],
        'image/png'       => ['png'],
    ];

    public function __construct(
        private readonly AttachmentStorageInterface $storage,
        private readonly Storage $config,
    ) {
    }

    public function uploadForMovement(int $movementId, UploadedFile $file, int $userId): int
    {
        if ($movementId <= 0 || db_connect()->table('inventory_movements')->where('id', $movementId)->countAllResults() !== 1) {
            throw new DomainException('El movimiento no existe.');
        }
        if (! $file->isValid() || $file->hasMoved()) {
            throw new DomainException('El archivo no fue recibido correctamente.');
        }

        $size = $file->getSize();
        if ($size <= 0 || $size > $this->config->maxUploadBytes) {
            throw new DomainException('El archivo debe pesar como máximo 10 MB.');
        }

        $extension = strtolower(pathinfo($file->getClientName(), PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($file->getTempName());
        if (! isset(self::ALLOWED_TYPES[$mimeType]) || ! in_array($extension, self::ALLOWED_TYPES[$mimeType], true)) {
            throw new DomainException('Solo se permiten archivos PDF, JPG, JPEG o PNG válidos.');
        }

        $originalName = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', basename($file->getClientName())) ?? 'documento.' . $extension);
        if ($originalName === '' || mb_strlen($originalName) > 255) {
            throw new DomainException('El nombre original del archivo no es válido.');
        }

        $internalName = bin2hex(random_bytes(20)) . '.' . $extension;
        $objectKey = sprintf('movements/%d/%s/%s', $movementId, date('Y/m'), $internalName);
        $sha256 = hash_file('sha256', $file->getTempName());
        if ($sha256 === false) {
            throw new RuntimeException('No fue posible verificar la integridad del archivo.');
        }

        $this->storage->put($objectKey, $file->getTempName(), $mimeType);
        try {
            $db = db_connect();
            $db->table('inventory_attachments')->insert([
                'movement_id'    => $movementId,
                'original_name'  => $originalName,
                'internal_name'  => $internalName,
                'object_key'     => $objectKey,
                'storage_driver' => $this->storage->driver(),
                'mime_type'      => $mimeType,
                'extension'      => $extension,
                'size_bytes'     => $size,
                'sha256'         => $sha256,
                'uploaded_by'    => $userId,
                'uploaded_at'    => date('Y-m-d H:i:s'),
                'status'         => 'ACTIVE',
            ]);

            return (int) $db->insertID();
        } catch (Throwable $exception) {
            $this->storage->discardUncommitted($objectKey);
            throw $exception;
        }
    }

    /** @return array{record: array<string, mixed>, contents: string} */
    public function download(int $attachmentId): array
    {
        $record = db_connect()->table('inventory_attachments')->where('id', $attachmentId)->where('status', 'ACTIVE')->get()->getRowArray();
        if ($record === null) {
            throw new DomainException('El archivo no existe o está archivado.');
        }
        if ($record['storage_driver'] !== $this->storage->driver()) {
            throw new RuntimeException('El archivo pertenece a un almacenamiento no disponible en este entorno.');
        }

        return ['record' => $record, 'contents' => $this->storage->read((string) $record['object_key'])];
    }

    public function archive(int $attachmentId, string $reason, int $userId): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
            throw new DomainException('El motivo de archivo debe tener entre 5 y 1000 caracteres.');
        }

        $db = db_connect();
        $db->table('inventory_attachments')
            ->where('id', $attachmentId)
            ->where('status', 'ACTIVE')
            ->update([
                'status'         => 'ARCHIVED',
                'archived_by'    => $userId,
                'archived_at'    => date('Y-m-d H:i:s'),
                'archive_reason' => $reason,
            ]);
        if ($db->affectedRows() !== 1) {
            throw new DomainException('El archivo no existe o ya está archivado.');
        }
    }
}
