<?php

declare(strict_types=1);

namespace App\Contracts;

interface AttachmentStorageInterface
{
    public function driver(): string;

    public function put(string $objectKey, string $sourcePath, string $mimeType): void;

    public function read(string $objectKey): string;

    /** Used only to compensate a failed upload before its metadata is committed. */
    public function discardUncommitted(string $objectKey): void;
}
