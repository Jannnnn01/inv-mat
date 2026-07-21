<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class DatabaseUrl
{
    /** @return array<string, mixed> */
    public static function parse(string $url): array
    {
        $parts = parse_url(trim($url));
        if ($parts === false
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['postgres', 'postgresql'], true)
            || empty($parts['host'])
            || empty($parts['user'])
            || empty($parts['path'])) {
            throw new InvalidArgumentException('DATABASE_URL no tiene un formato PostgreSQL válido.');
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $sslMode = strtolower((string) ($query['sslmode'] ?? 'require'));
        if (! in_array($sslMode, ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
            throw new InvalidArgumentException('DATABASE_URL contiene un modo SSL no válido.');
        }

        return [
            'DSN'      => '',
            'hostname' => (string) $parts['host'],
            'username' => rawurldecode((string) $parts['user']),
            'password' => rawurldecode((string) ($parts['pass'] ?? '')),
            'database' => rawurldecode(ltrim((string) $parts['path'], '/')),
            'port'     => (int) ($parts['port'] ?? 5432),
            'schema'   => 'public',
            'DBDriver' => 'Postgre',
            'DBDebug'  => false,
            'pConnect' => false,
            'sslmode'  => $sslMode,
        ];
    }
}
