<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Throwable;

final class AuditFilter implements FilterInterface
{
    /** @var array{id: int|null, name: string|null}|null */
    private ?array $actor = null;

    private ?string $resourceTable = null;

    private ?int $resourceId = null;

    /** @var array<string, mixed>|null */
    private ?array $oldValues = null;

    public function before(RequestInterface $request, $arguments = null)
    {
        $this->actor = null;
        $this->resourceTable = null;
        $this->resourceId = null;
        $this->oldValues = null;

        try {
            $user = auth()->user();
            $this->actor = [
                'id'   => $user?->id !== null ? (int) $user->id : null,
                'name' => $user?->username ?? $user?->email,
            ];
            $this->identifyResource($request);
            $this->oldValues = $this->resourceSnapshot();
        } catch (Throwable) {
            $this->actor = null;
            $this->oldValues = null;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        try {
            Services::audit()->recordHttp(
                $request,
                $response,
                $this->actor,
                $this->oldValues,
                $this->resourceSnapshot(),
            );
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible registrar un evento de auditoría: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function identifyResource(RequestInterface $request): void
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return;
        }

        $path = trim($request->getUri()->getPath(), '/');
        $patterns = [
            '#^admin/usuarios/(\d+)/(?:estado|rol)$#'          => 'users',
            '#^catalogos/bodegas/(\d+)(?:/estado)?$#'         => 'warehouses',
            '#^catalogos/unidades/(\d+)(?:/estado)?$#'        => 'measurement_units',
            '#^catalogos/categorias/(\d+)(?:/estado)?$#'      => 'categories',
            '#^catalogos/proveedores/(\d+)(?:/estado)?$#'     => 'suppliers',
            '#^catalogos/materiales/(\d+)(?:/estado)?$#'      => 'materials',
            '#^inventario/solicitudes/(\d+)/decision$#'       => 'inventory_requests',
            '#^inventario/archivos/(\d+)/archivar$#'          => 'inventory_attachments',
        ];

        foreach ($patterns as $pattern => $table) {
            if (preg_match($pattern, $path, $matches) === 1) {
                $this->resourceTable = $table;
                $this->resourceId = (int) $matches[1];

                return;
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function resourceSnapshot(): ?array
    {
        if ($this->resourceTable === null || $this->resourceId === null) {
            return null;
        }

        $database = db_connect();
        if ($this->resourceTable === 'users') {
            $auth = config('Auth');
            $row = $database->table($auth->tables['users'] . ' users')
                ->select('users.id, users.username, users.active, users.created_at, users.updated_at, groups.group AS role')
                ->join($auth->tables['groups_users'] . ' groups', 'groups.user_id = users.id', 'left')
                ->where('users.id', $this->resourceId)
                ->get()
                ->getRowArray();

            return $row === null ? null : $row;
        }

        $row = $database->table($this->resourceTable)
            ->where('id', $this->resourceId)
            ->get()
            ->getRowArray();

        return $row === null ? null : $row;
    }
}
