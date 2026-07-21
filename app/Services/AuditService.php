<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use JsonException;

final class AuditService
{
    private const PROTECTED_KEY_PARTS = [
        'password', 'passwd', 'token', 'secret', 'csrf', 'cookie', 'credential', 'api_key', 'apikey',
        'access_key', 'accesskey', 'private_key', 'encryption_key',
    ];

    private ?BaseConnection $database;

    public function __construct(?BaseConnection $database = null)
    {
        $this->database = $database;
    }

    /**
     * @param array{id: int|null, name: string|null}|null $actor
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $currentValues
     */
    public function recordHttp(
        RequestInterface $request,
        ResponseInterface $response,
        ?array $actor = null,
        ?array $oldValues = null,
        ?array $currentValues = null,
    ): void
    {
        $method = strtoupper($request->getMethod());
        $path = trim($request->getUri()->getPath(), '/');

        if (! $this->shouldAudit($method, $path)) {
            return;
        }

        $segments = $path === '' ? [] : explode('/', $path);
        $routeOptions = service('router')->getMatchedRouteOptions();
        $routeName = isset($routeOptions['as']) ? (string) $routeOptions['as'] : null;
        $payload = $method === 'GET' ? $request->getGet() : $request->getPost();
        $result = $this->resolveResult($response);
        $user = auth()->user();
        if ($actor === null || ($actor['id'] === null && $user?->id !== null)) {
            $actor = [
                'id'   => $user?->id !== null ? (int) $user->id : null,
                'name' => $user?->username ?? $user?->email,
            ];
        }

        $newValues = $payload === [] ? null : ['input' => $this->sanitize($payload)];
        if ($currentValues !== null) {
            $newValues ??= [];
            $newValues['record'] = $this->sanitize($currentValues);
        }

        $this->record([
            'actor_user_id' => $actor['id'],
            'actor_name'    => $actor['name'],
            'action'        => $routeName ?? strtolower($method) . ':' . ($path === '' ? '/' : $path),
            'module'        => $this->moduleFrom($segments[0] ?? 'public'),
            'route_name'    => $routeName,
            'http_method'   => $method,
            'request_path'  => '/' . $path,
            'entity_type'   => $segments[1] ?? null,
            'entity_id'     => $this->firstNumericSegment($segments),
            'result'        => $result,
            'ip_address'    => $request->getIPAddress(),
            'user_agent'    => $this->summarizeUserAgent($request),
            'old_values'    => $oldValues === null ? null : $this->sanitize($oldValues),
            'new_values'    => $newValues,
        ]);
    }

    /**
     * @param array<string, mixed> $event
     */
    public function record(array $event): void
    {
        $occurredAt = date('Y-m-d H:i:s');
        $data = [
            'actor_user_id'   => $event['actor_user_id'] ?? null,
            'actor_name'      => $this->limitNullable($event['actor_name'] ?? null, 191),
            'action'          => $this->limit((string) ($event['action'] ?? 'unknown'), 120),
            'module'          => $this->limit((string) ($event['module'] ?? 'unknown'), 80),
            'route_name'      => $this->limitNullable($event['route_name'] ?? null, 120),
            'http_method'     => $this->limit(strtoupper((string) ($event['http_method'] ?? 'POST')), 10),
            'request_path'    => $this->limit((string) ($event['request_path'] ?? '/'), 500),
            'entity_type'     => $this->limitNullable($event['entity_type'] ?? null, 100),
            'entity_id'       => $this->limitNullable($event['entity_id'] ?? null, 100),
            'result'          => in_array($event['result'] ?? null, ['SUCCESS', 'FAILURE', 'DENIED'], true) ? $event['result'] : 'FAILURE',
            'ip_address'      => $this->limitNullable($event['ip_address'] ?? null, 45),
            'user_agent'      => $this->limitNullable($event['user_agent'] ?? null, 255),
            'old_values'      => $this->encodeNullable($event['old_values'] ?? null),
            'new_values'      => $this->encodeNullable($event['new_values'] ?? null),
            'occurred_at'     => $occurredAt,
            'retention_until' => date('Y-m-d', strtotime($occurredAt . ' +2 years')),
        ];

        ($this->database ??= db_connect())->table('audit_events')->insert($data);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function sanitize($value, int $depth = 0)
    {
        if ($depth >= 6) {
            return '[TRUNCATED]';
        }

        if (! is_array($value)) {
            return is_string($value) ? $this->limit($value, 2000) : $value;
        }

        $sanitized = [];
        $count = 0;
        foreach ($value as $key => $item) {
            if (++$count > 100) {
                $sanitized['_truncated'] = true;
                break;
            }

            $keyString = (string) $key;
            $sanitized[$key] = $this->isProtectedKey($keyString)
                ? '[PROTECTED]'
                : $this->sanitize($item, $depth + 1);
        }

        return $sanitized;
    }

    private function shouldAudit(string $method, string $path): bool
    {
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        return $method === 'GET'
            && (str_starts_with($path, 'admin/')
                || str_starts_with($path, 'reportes')
                || preg_match('#^inventario/archivos/\d+/descargar$#', $path) === 1);
    }

    private function resolveResult(ResponseInterface $response): string
    {
        if ($response->getStatusCode() === 401 || $response->getStatusCode() === 403) {
            return 'DENIED';
        }

        if ($response->getStatusCode() >= 400 || session('error') !== null || session('errors') !== null) {
            return 'FAILURE';
        }

        return 'SUCCESS';
    }

    /** @param list<string> $segments */
    private function firstNumericSegment(array $segments): ?string
    {
        foreach ($segments as $segment) {
            if (ctype_digit($segment)) {
                return $segment;
            }
        }

        return null;
    }

    private function moduleFrom(string $segment): string
    {
        return match ($segment) {
            'admin'     => 'administration',
            'catalogos' => 'catalogs',
            'inventario'=> 'inventory',
            'mi-cuenta' => 'account',
            'login', 'auth' => 'authentication',
            default     => 'public',
        };
    }

    private function summarizeUserAgent(RequestInterface $request): ?string
    {
        if (! method_exists($request, 'getUserAgent')) {
            return null;
        }

        $agent = $request->getUserAgent();
        if ($agent->isRobot()) {
            return $this->limit('Robot: ' . $agent->getRobot(), 255);
        }

        $parts = array_filter([
            trim($agent->getBrowser() . ' ' . $agent->getVersion()),
            $agent->getPlatform(),
            $agent->getMobile() !== '' ? 'Mobile: ' . $agent->getMobile() : null,
        ]);

        return $parts === [] ? null : $this->limit(implode(' / ', $parts), 255);
    }

    private function isProtectedKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));
        foreach (self::PROTECTED_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }

    /** @param mixed $value */
    private function encodeNullable($value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        try {
            return json_encode($this->sanitize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return json_encode(['_error' => 'Payload unavailable']);
        }
    }

    /** @param mixed $value */
    private function limitNullable($value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->limit((string) $value, $length);
    }

    private function limit(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }
}
