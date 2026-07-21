<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditEventModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use DateTimeImmutable;

final class AuditController extends BaseController
{
    public function index(): string
    {
        $filters = $this->filters();
        $model = model(AuditEventModel::class);
        $events = $model
            ->applyFilters($filters)
            ->orderBy('occurred_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->paginate(25);
        $canViewSensitive = auth()->user()?->can('audit.sensitive') ?? false;

        return view('admin/audit/index', [
            'events'           => array_map(fn (array $event): array => $this->present($event, $canViewSensitive), $events),
            'pager'            => $model->pager,
            'filters'          => $filters,
            'actors'           => $this->actors(),
            'modules'          => $this->modules(),
            'canViewSensitive' => $canViewSensitive,
        ]);
    }

    public function show(int $id): string
    {
        $event = model(AuditEventModel::class)->find($id);
        if (! is_array($event)) {
            throw PageNotFoundException::forPageNotFound('El evento de auditoría no existe.');
        }

        $canViewSensitive = auth()->user()?->can('audit.sensitive') ?? false;
        $event = $this->present($event, $canViewSensitive);
        $event['old_values_decoded'] = $this->decode($event['old_values'] ?? null);
        $event['new_values_decoded'] = $this->decode($event['new_values'] ?? null);

        return view('admin/audit/show', [
            'event'            => $event,
            'canViewSensitive' => $canViewSensitive,
        ]);
    }

    /** @return array{date_from: string, date_to: string, actor_user_id: int, module: string, action: string, result: string} */
    private function filters(): array
    {
        $dateFrom = $this->validDate((string) $this->request->getGet('date_from'));
        $dateTo = $this->validDate((string) $this->request->getGet('date_to'));
        $actorId = filter_var($this->request->getGet('actor_user_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $module = trim((string) $this->request->getGet('module'));
        $action = trim((string) $this->request->getGet('action'));
        $result = strtoupper(trim((string) $this->request->getGet('result')));

        return [
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'actor_user_id'=> $actorId === false ? 0 : (int) $actorId,
            'module'       => preg_match('/\A[a-z][a-z0-9_-]{0,79}\z/', $module) === 1 ? $module : '',
            'action'       => mb_substr($action, 0, 120),
            'result'       => in_array($result, ['SUCCESS', 'FAILURE', 'DENIED'], true) ? $result : '',
        ];
    }

    private function validDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }

    /** @return list<array{actor_user_id: int, actor_name: string}> */
    private function actors(): array
    {
        return db_connect()->table('audit_events')
            ->select('actor_user_id, MAX(actor_name) AS actor_name')
            ->where('actor_user_id IS NOT NULL', null, false)
            ->groupBy('actor_user_id')
            ->orderBy('actor_name')
            ->get()
            ->getResultArray();
    }

    /** @return list<string> */
    private function modules(): array
    {
        $rows = db_connect()->table('audit_events')
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->get()
            ->getResultArray();

        return array_column($rows, 'module');
    }

    /** @param array<string, mixed> $event @return array<string, mixed> */
    private function present(array $event, bool $canViewSensitive): array
    {
        if (! $canViewSensitive) {
            $event['ip_address'] = $this->maskIp($event['ip_address'] ?? null);
        }

        return $event;
    }

    private function maskIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);

            return implode(':', array_slice($parts, 0, 3)) . ':…';
        }

        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $parts[3] = 'x';

            return implode('.', $parts);
        }

        return 'Protegida';
    }

    /** @return array<string, mixed>|list<mixed>|null */
    private function decode($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
