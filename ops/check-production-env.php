<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\DatabaseUrl;

$required = [
    'DATABASE_URL',
    'encryption_key',
    'email_fromEmail',
    'email_SMTPHost',
    'storage_bucket',
    'storage_region',
    'storage_accessKey',
    'storage_secretKey',
];

$missing = [];
foreach ($required as $name) {
    $value = getenv($name);
    if (! is_string($value) || trim($value) === '') {
        $missing[] = $name;
    }
}

if (getenv('CI_ENVIRONMENT') !== 'production') {
    $missing[] = 'CI_ENVIRONMENT=production';
}
if (getenv('storage_driver') !== 's3') {
    $missing[] = 'storage_driver=s3';
}

try {
    DatabaseUrl::parse((string) getenv('DATABASE_URL'));
} catch (Throwable) {
    $missing[] = 'DATABASE_URL válida con SSL';
}

if ($missing !== []) {
    fwrite(STDERR, 'Configuración productiva incompleta: ' . implode(', ', array_unique($missing)) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Configuración productiva validada sin mostrar secretos.' . PHP_EOL);
