<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class Storage extends BaseConfig
{
    public string $driver = 'local';
    public string $localPath = WRITEPATH . 'private-uploads';
    public int $maxUploadBytes = 10_485_760;
    public string $bucket = '';
    public string $region = 'us-east-1';
    public string $endpoint = '';
    public string $accessKey = '';
    public string $secretKey = '';
    public bool $pathStyle = false;
}
