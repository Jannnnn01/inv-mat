<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\DatabaseUrl;

$required = [
    'DATABASE_URL',
    'encryption_key',
    'email_fromEmail',
    'email_SMTPHost',
    'email_SMTPUser',
    'email_SMTPPass',
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
if (getenv('email_protocol') !== 'smtp') {
    $missing[] = 'email_protocol=smtp';
}

$fromEmail = getenv('email_fromEmail');
if (! is_string($fromEmail) || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
    $missing[] = 'email_fromEmail válido';
}

$smtpPort = filter_var(getenv('email_SMTPPort'), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);
if ($smtpPort === false) {
    $missing[] = 'email_SMTPPort válido';
}

$smtpCrypto = getenv('email_SMTPCrypto');
if (! in_array($smtpCrypto, ['tls', 'ssl'], true)) {
    $missing[] = 'email_SMTPCrypto=tls o ssl';
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
