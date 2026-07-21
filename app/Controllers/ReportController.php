<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\MaterialModel;
use App\Models\WarehouseModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserModel;
use Config\Services;
use DateTimeImmutable;
use Throwable;

final class ReportController extends BaseController
{
    private const SCREEN_LIMIT = 500;
    private const EXPORT_LIMIT = 10000;

    public function index(): string
    {
        $type = (string) ($this->request->getGet('report') ?: 'current-stock');
        $service = Services::reports();
        $this->assertAllowed($type);
        $filters = $this->filters();
        $lookups = $this->lookups();
        $definitions = $this->availableDefinitions($service->definitions());

        return view('reports/index', [
            'definitions' => $definitions,
            'report'      => $service->generate(
                $type,
                $filters,
                auth()->user()?->can('financial.view') ?? false,
                auth()->user()?->can('audit.sensitive') ?? false,
                self::SCREEN_LIMIT,
            ),
            'filters'     => $filters,
            'lookups'     => $lookups,
            'queryString' => http_build_query($this->activeFilters($filters)),
            'canExport'   => auth()->user()?->can('reports.export') ?? false,
        ]);
    }

    public function csv(string $type): ResponseInterface|RedirectResponse
    {
        return $this->export($type, 'csv');
    }

    public function pdf(string $type): ResponseInterface|RedirectResponse
    {
        return $this->export($type, 'pdf');
    }

    private function export(string $type, string $format): ResponseInterface|RedirectResponse
    {
        $this->assertAllowed($type);
        $filters = $this->filters();

        try {
            $report = Services::reports()->generate(
                $type,
                $filters,
                auth()->user()?->can('financial.view') ?? false,
                auth()->user()?->can('audit.sensitive') ?? false,
                self::EXPORT_LIMIT,
            );

            if ($report['limited']) {
                return redirect()->to(url_to('reports') . '?' . http_build_query(['report' => $type] + $this->activeFilters($filters)))
                    ->with('error', 'La exportación supera 10.000 filas. Reduce el rango o aplica más filtros.');
            }

            $filename = 'reporte-' . $type . '-' . date('Ymd-His') . '.' . $format;
            if ($format === 'csv') {
                return $this->response
                    ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                    ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                    ->setBody(Services::reportExports()->csv($report));
            }

            $lookups = $this->lookups();

            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody(Services::reportExports()->pdf([
                    'report'        => $report,
                    'filterSummary' => $this->filterSummary($filters, $lookups),
                    'generatedAt'   => date('Y-m-d H:i:s'),
                ]));
        } catch (Throwable $exception) {
            log_message('error', 'No fue posible exportar el reporte {type} en {format}: {message}', [
                'type' => $type, 'format' => $format, 'message' => $exception->getMessage(),
            ]);

            return redirect()->to(url_to('reports') . '?' . http_build_query(['report' => $type] + $this->activeFilters($filters)))
                ->with('error', 'No fue posible generar el archivo solicitado.');
        }
    }

    private function assertAllowed(string $type): void
    {
        if (! Services::reports()->exists($type)) {
            throw PageNotFoundException::forPageNotFound('El reporte solicitado no existe.');
        }

        if ($type === 'administrative-audit' && ! (auth()->user()?->can('audit.view') ?? false)) {
            throw PageNotFoundException::forPageNotFound('El reporte solicitado no está disponible.');
        }
    }

    /** @return array<string, array{title: string, description: string}> */
    private function availableDefinitions(array $definitions): array
    {
        if (! (auth()->user()?->can('audit.view') ?? false)) {
            unset($definitions['administrative-audit']);
        }

        return $definitions;
    }

    /** @return array{from: string, to: string, material_id: int, category_id: int, warehouse_id: int, user_id: int, movement_type: string, audit_module: string, audit_result: string, action: string} */
    private function filters(): array
    {
        $movementType = strtoupper(trim((string) $this->request->getGet('movement_type')));
        $auditResult = strtoupper(trim((string) $this->request->getGet('audit_result')));
        $auditModule = strtolower(trim((string) $this->request->getGet('audit_module')));

        return [
            'from'          => $this->validDate((string) $this->request->getGet('from')),
            'to'            => $this->validDate((string) $this->request->getGet('to')),
            'material_id'   => $this->positiveId($this->request->getGet('material_id')),
            'category_id'   => $this->positiveId($this->request->getGet('category_id')),
            'warehouse_id'  => $this->positiveId($this->request->getGet('warehouse_id')),
            'user_id'       => $this->positiveId($this->request->getGet('user_id')),
            'movement_type' => in_array($movementType, ['ENTRY', 'EXIT', 'ADJUSTMENT', 'REVERSAL'], true) ? $movementType : '',
            'audit_module'  => preg_match('/\A[a-z][a-z0-9_-]{0,79}\z/', $auditModule) === 1 ? $auditModule : '',
            'audit_result'  => in_array($auditResult, ['SUCCESS', 'FAILURE', 'DENIED'], true) ? $auditResult : '',
            'action'        => mb_substr(trim((string) $this->request->getGet('action')), 0, 120),
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

    private function positiveId($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? 0 : (int) $id;
    }

    /** @return array{materials: list<array<string, mixed>>, categories: list<array<string, mixed>>, warehouses: list<array<string, mixed>>, users: list<object>, auditModules: list<string>} */
    private function lookups(): array
    {
        $moduleRows = [];
        if (auth()->user()?->can('audit.view') ?? false) {
            $moduleRows = db_connect()->table('audit_events')->select('module')->distinct()->orderBy('module')->get()->getResultArray();
        }

        return [
            'materials'   => model(MaterialModel::class)->orderBy('name')->findAll(),
            'categories'  => model(CategoryModel::class)->orderBy('name')->findAll(),
            'warehouses'  => model(WarehouseModel::class)->orderBy('name')->findAll(),
            'users'       => model(UserModel::class)->orderBy('username')->findAll(),
            'auditModules'=> array_column($moduleRows, 'module'),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function activeFilters(array $filters): array
    {
        return array_filter($filters, static fn ($value): bool => $value !== '' && $value !== 0);
    }

    /** @param array<string, mixed> $filters @param array<string, mixed> $lookups @return list<string> */
    private function filterSummary(array $filters, array $lookups): array
    {
        $summary = [];
        if ($filters['from'] !== '') {
            $summary[] = 'Desde: ' . $filters['from'];
        }
        if ($filters['to'] !== '') {
            $summary[] = 'Hasta: ' . $filters['to'];
        }
        foreach (['material_id' => ['materials', 'Material'], 'category_id' => ['categories', 'Categoría'], 'warehouse_id' => ['warehouses', 'Bodega']] as $filter => [$lookup, $label]) {
            if ($filters[$filter] > 0) {
                foreach ($lookups[$lookup] as $item) {
                    if ((int) $item['id'] === $filters[$filter]) {
                        $summary[] = $label . ': ' . $item['name'];
                        break;
                    }
                }
            }
        }
        if ($filters['movement_type'] !== '') {
            $summary[] = 'Tipo: ' . $filters['movement_type'];
        }
        if ($filters['user_id'] > 0) {
            foreach ($lookups['users'] as $user) {
                if ((int) $user->id === $filters['user_id']) {
                    $summary[] = 'Usuario: ' . $user->username;
                    break;
                }
            }
        }
        if ($filters['audit_module'] !== '') {
            $summary[] = 'Módulo: ' . $filters['audit_module'];
        }
        if ($filters['audit_result'] !== '') {
            $summary[] = 'Resultado: ' . $filters['audit_result'];
        }
        if ($filters['action'] !== '') {
            $summary[] = 'Acción contiene: ' . $filters['action'];
        }

        return $summary;
    }
}
