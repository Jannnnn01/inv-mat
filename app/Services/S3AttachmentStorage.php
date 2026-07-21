<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AttachmentStorageInterface;
use Aws\S3\S3Client;
use Config\Storage;
use RuntimeException;
use Throwable;

final class S3AttachmentStorage implements AttachmentStorageInterface
{
    private S3Client $client;

    public function __construct(private readonly Storage $config)
    {
        if ($config->bucket === '' || $config->accessKey === '' || $config->secretKey === '') {
            throw new RuntimeException('El almacenamiento S3 no está configurado.');
        }

        $options = [
            'version'                 => 'latest',
            'region'                  => $config->region,
            'credentials'             => ['key' => $config->accessKey, 'secret' => $config->secretKey],
            'use_path_style_endpoint' => $config->pathStyle,
        ];
        if ($config->endpoint !== '') {
            $options['endpoint'] = $config->endpoint;
        }
        $this->client = new S3Client($options);
    }

    public function driver(): string
    {
        return 's3';
    }

    public function put(string $objectKey, string $sourcePath, string $mimeType): void
    {
        $this->client->putObject([
            'Bucket'      => $this->config->bucket,
            'Key'         => $objectKey,
            'SourceFile'  => $sourcePath,
            'ContentType' => $mimeType,
        ]);
    }

    public function read(string $objectKey): string
    {
        $result = $this->client->getObject(['Bucket' => $this->config->bucket, 'Key' => $objectKey]);

        return (string) $result['Body'];
    }

    public function discardUncommitted(string $objectKey): void
    {
        try {
            $this->client->deleteObject(['Bucket' => $this->config->bucket, 'Key' => $objectKey]);
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible limpiar un objeto S3 no confirmado: {message}', ['message' => $exception->getMessage()]);
        }
    }
}
