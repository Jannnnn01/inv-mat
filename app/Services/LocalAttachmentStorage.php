<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AttachmentStorageInterface;
use RuntimeException;

final class LocalAttachmentStorage implements AttachmentStorageInterface
{
    public function __construct(private readonly string $rootPath)
    {
    }

    public function driver(): string
    {
        return 'local';
    }

    public function put(string $objectKey, string $sourcePath, string $mimeType): void
    {
        $target = $this->path($objectKey);
        $directory = dirname($target);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible preparar el almacenamiento privado.');
        }
        if (! copy($sourcePath, $target)) {
            throw new RuntimeException('No fue posible almacenar el archivo.');
        }
        @chmod($target, 0600);
    }

    public function read(string $objectKey): string
    {
        $path = $this->path($objectKey);
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new RuntimeException('El archivo solicitado no está disponible.');
        }

        return $contents;
    }

    public function discardUncommitted(string $objectKey): void
    {
        $path = $this->path($objectKey);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function path(string $objectKey): string
    {
        if (! preg_match('/\A[a-zA-Z0-9_\/-]+\.[a-z0-9]+\z/', $objectKey) || str_contains($objectKey, '..')) {
            throw new RuntimeException('La clave del archivo no es válida.');
        }

        return rtrim($this->rootPath, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $objectKey);
    }
}
