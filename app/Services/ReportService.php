<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

final class ReportService
{
    /** @var array<string, array{title: string, description: string}> */
    private const DEFINITIONS = [
        'current-stock'       => ['title' => 'Existencias actuales', 'description' => 'Saldo físico, reservado y disponible por material y bodega.'],
        'low-stock'           => ['title' => 'Materiales con stock bajo', 'description' => 'Disponibilidad igual o inferior al mínimo configurado.'],
        'kardex'              => ['title' => 'Kardex por material', 'description' => 'Secuencia histórica de entradas y salidas con saldo anterior y posterior.'],
        'entries'             => ['title' => 'Entradas por período', 'description' => 'Detalle de materiales recibidos durante el período seleccionado.'],
        'exits'               => ['title' => 'Salidas por período', 'description' => 'Detalle de materiales entregados durante el período seleccionado.'],
        'adjustments'         => ['title' => 'Ajustes y reversiones', 'description' => 'Solicitudes, decisiones y movimientos compensatorios relacionados.'],
        'movements-by-user'   => ['title' => 'Movimientos por usuario', 'description' => 'Actividad de inventario agrupada por usuario registrador.'],
        'most-used-materials' => ['title' => 'Materiales más utilizados', 'description' => 'Materiales con mayor cantidad de salida durante el período.'],
        'administrative-audit'=> ['title' => 'Auditoría administrativa', 'description' => 'Accesos y acciones administrativas registradas por el sistema.'],
    ];

    public function __construct(private readonly ?BaseConnection $database = null)
    {
    }

    /** @return array<string, array{title: string, description: string}> */
    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public function exists(string $type): bool
    {
        return isset(self::DEFINITIONS[$type]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{key: string, title: string, description: string, columns: array<string, string>, rows: list<array<string, mixed>>, limited: bool, message: string|null}
     */
    public function generate(
        string $type,
        array $filters,
        bool $showFinancial,
        bool $showSensitiveAudit,
        int $limit = 500,
    ): array {
        if (! $this->exists($type)) {
            throw new InvalidArgumentException('El tipo de reporte no es válido.');
        }

        $limit = max(1, min($limit, 10000));
        $message = null;
        [$columns, $rows] = match ($type) {
            'current-stock'        => $this->currentStock($filters, $showFinancial, $limit + 1),
            'low-stock'            => $this->lowStock($filters, $showFinancial, $limit + 1),
            'kardex'               => $this->kardex($filters, $showFinancial, $limit + 1),
            'entries'              => $this->movementDetails('ENTRY', $filters, $showFinancial, $limit + 1),
            'exits'                => $this->movementDetails('EXIT', $filters, $showFinancial, $limit + 1),
            'adjustments'          => $this->adjustments($filters, $limit + 1),
            'movements-by-user'    => $this->movementsByUser($filters, $limit + 1),
            'most-used-materials'  => $this->mostUsedMaterials($filters, $limit + 1),
            'administrative-audit' => $this->administrativeAudit($filters, $showSensitiveAudit, $limit + 1),
        };

        if ($type === 'kardex' && ($filters['material_id'] ?? 0) < 1) {
            $message = 'Selecciona un material para generar el kardex.';
        }

        $limited = count($rows) > $limit;
        if ($limited) {
            $rows = array_slice($rows, 0, $limit);
        }

        return [
            'key'         => $type,
            'title'       => self::DEFINITIONS[$type]['title'],
            'description' => self::DEFINITIONS[$type]['description'],
            'columns'     => $columns,
            'rows'        => $rows,
            'limited'     => $limited,
            'message'     => $message,
        ];
    }

    /** @return array{array<string, string>, list<array<string, mixed>>} */
    private function currentStock(array $filters, bool $financial, int $limit): array
    {
        $columns = [
            'warehouse' => 'Bodega', 'code' => 'Código', 'material' => 'Material', 'category' => 'Categoría',
            'unit' => 'Unidad', 'quantity' => 'Existencia física', 'reserved_quantity' => 'Reservado',
            'available_quantity' => 'Disponible', 'minimum_stock' => 'Mínimo', 'status' => 'Estado',
        ];
        $financialSelect = '';
        if ($financial) {
            $columns += [
                'valued_quantity' => 'Cantidad valorada', 'average_unit_cost' => 'Costo promedio',
                'total_value' => 'Valor total', 'valuation_status' => 'Valoración',
            ];
            $financialSelect = ", COALESCE(s.valued_quantity, 0) AS valued_quantity,
                COALESCE(s.average_unit_cost, 0) AS average_unit_cost,
                COALESCE(s.total_value, 0) AS total_value,
                CASE WHEN COALESCE(s.valued_quantity, 0) < COALESCE(s.quantity, 0) THEN 'Pendiente' ELSE 'Completa' END AS valuation_status";
        }

        $sql = "SELECT w.name AS warehouse, m.code, m.name AS material, c.name AS category, u.symbol AS unit,
                       COALESCE(s.quantity, 0) AS quantity, COALESCE(r.reserved_quantity, 0) AS reserved_quantity,
                       COALESCE(s.quantity, 0) - COALESCE(r.reserved_quantity, 0) AS available_quantity, m.minimum_stock,
                       CASE WHEN COALESCE(s.quantity, 0) - COALESCE(r.reserved_quantity, 0) <= m.minimum_stock THEN 'Stock bajo' ELSE 'Normal' END AS status
                       {$financialSelect}
                  FROM materials m
                  JOIN categories c ON c.id = m.category_id
                  JOIN measurement_units u ON u.id = m.unit_id
                 CROSS JOIN warehouses w
                  LEFT JOIN inventory_stocks s ON s.material_id = m.id AND s.warehouse_id = w.id
                  LEFT JOIN (
                    SELECT warehouse_id, material_id, SUM(quantity_remaining) AS reserved_quantity
                      FROM inventory_reservations WHERE status = 'ACTIVE'
                     GROUP BY warehouse_id, material_id
                  ) r ON r.material_id = m.id AND r.warehouse_id = w.id
                 WHERE m.active = TRUE AND w.active = TRUE";
        $params = [];
        $this->appendCatalogFilters($sql, $params, $filters, 'm', 'w');
        $sql .= ' ORDER BY w.name, m.name LIMIT ?';
        $params[] = $limit;

        return [$columns, $this->db()->query($sql, $params)->getResultArray()];
    }

    /** @return array{array<string, string>, list<array<string, mixed>>} */
    private function lowStock(array $filters, bool $financial, int $limit): array
    {
        $columns = [
            'warehouse' => 'Bodega', 'code' => 'Código', 'material' => 'Material', 'category' => 'Categoría',
            'unit' => 'Unidad', 'quantity' => 'Existencia física', 'reserved_quantity' => 'Reservado',
            'available_quantity' => 'Disponible', 'minimum_stock' => 'Mínimo', 'deficit' => 'Déficit',
        ];
        $financialSelect = '';
        if ($financial) {
            $columns += ['total_value' => 'Valor registrado', 'valuation_status' => 'Valoración'];
            $financialSelect = ", COALESCE(s.total_value, 0) AS total_value,
                CASE WHEN COALESCE(s.valued_quantity, 0) < COALESCE(s.quantity, 0) THEN 'Pendiente' ELSE 'Completa' END AS valuation_status";
        }

        $sql = "SELECT w.name AS warehouse, m.code, m.name AS material, c.name AS category, u.symbol AS unit,
                       COALESCE(s.quantity, 0) AS quantity, COALESCE(r.reserved_quantity, 0) AS reserved_quantity,
                       COALESCE(s.quantity, 0) - COALESCE(r.reserved_quantity, 0) AS available_quantity, m.minimum_stock,
                       GREATEST(m.minimum_stock - (COALESCE(s.quantity, 0) - COALESCE(r.reserved_quantity, 0)), 0) AS deficit
                       {$financialSelect}
                  FROM materials m
                  JOIN categories c ON c.id = m.category_id
                  JOIN measurement_units u ON u.id = m.unit_id
                 CROSS JOIN warehouses w
                  LEFT JOIN inventory_stocks s ON s.material_id = m.id AND s.warehouse_id = w.id
                  LEFT JOIN (
                    SELECT warehouse_id, material_id, SUM(quantity_remaining) AS reserved_quantity
                      FROM inventory_reservations WHERE status = 'ACTIVE'
                     GROUP BY warehouse_id, material_id
                  ) r ON r.material_id = m.id AND r.warehouse_id = w.id
                 WHERE m.active = TRUE AND w.active = TRUE
                   AND COALESCE(s.quantity, 0) - COALESCE(r.reserved_quantity, 0) <= m.minimum_stock";
        $params = [];
        $this->appendCatalogFilters($sql, $params, $filters, 'm', 'w');
        $sql .= ' ORDER BY deficit DESC, w.name, m.name LIMIT ?';
        $params[] = $limit;

        return [$columns, $this->db()->query($sql, $params)->getResultArray()];
    }

    /** @return array{array<string, string>, list<array<string, mixed>>} */
    private function kardex(array $filters, bool $financial, int $limit): array
    {
        $columns = [
            'date' => 'Fecha', 'movement_number' => 'Movimiento', 'movement_type' => 'Tipo', 'warehouse' => 'Bodega',
            'direction' => 'Dirección', 'quantity' => 'Cantidad', 'unit' => 'Unidad', 'stock_before' => 'Saldo anterior',
            'stock_after' => 'Saldo posterior', 'document_number' => 'Documento', 'created_by' => 'Registrado por',
        ];
        $financialSelect = '';
        if ($financial) {
            $columns += ['unit_cost' => 'Costo unitario', 'line_value' => 'Valor línea'];
            $financialSelect = ', i.unit_cost, i.line_value';
        }

        if (($filters['material_id'] ?? 0) < 1) {
            return [$columns, []];
        }

        $sql = "SELECT mv.created_at AS date, mv.movement_number,
                       CASE mv.type WHEN 'ENTRY' THEN 'Entrada' WHEN 'EXIT' THEN 'Salida' WHEN 'ADJUSTMENT' THEN 'Ajuste' ELSE 'Reversión' END AS movement_type,
                       w.name AS warehouse, CASE i.direction WHEN 1 THEN 'Entrada' ELSE 'Salida' END AS direction,
                       i.quantity, u.symbol AS unit, i.stock_before, i.stock_after, mv.document_number,
                       creator.username AS created_by {$financialSelect}
                  FROM inventory_movement_items i
                  JOIN inventory_movements mv ON mv.id = i.movement_id
                  JOIN materials m ON m.id = i.material_id
                  JOIN measurement_units u ON u.id = m.unit_id
                  JOIN warehouses w ON w.id = mv.warehouse_id
                  JOIN users creator ON creator.id = mv.created_by
                 WHERE i.material_id = ?";
        $params = [(int) $filters['material_id']];
        $this->appendMovementFilters($sql, $params, $filters, 'mv', 'm');
        $sql .= ' ORDER BY mv.created_at, mv.id, i.id LIMIT ?';
        $params[] = $limit;

        return [$columns, $this->db()->query($sql, $params)->getResultArray()];
    }

    /** @return array{array<string, string>, list<array<string, mixed>>} */
    private function movementDetails(string $type, array $filters, bool $financial, int $limit): array
    {
        $isEntry = $type === 'ENTRY';
        $columns = [
            'date' => 'Fecha', 'movement_number' => 'Movimiento', 'warehouse' => 'Bodega', 'document_number' => 'Documento',
            'material_code' => 'Código', 'material' => 'Material', 'category' => 'Categoría', 'quantity' => 'Cantidad',
            'unit' => 'Unidad', 'delivered_by' => 'Entrega', 'received_by' => 'Recibe', 'area' => 'Área',
            'created_by' => 'Registrado por',
        ];
        if ($isEntry) {
            $columns = array_slice($columns, 0, 4, true) + ['supplier' => 'Proveedor'] + array_slice($columns, 4, null, true);
        }
        $financialSelect = '';
        if ($financial) {
            $columns += ['unit_cost' => 'Costo unitario', 'line_value' => 'Valor línea', 'valuation_status' => 'Valoración'];
            $financialSelect = ", i.unit_cost, i.line_value,
                CASE WHEN i.pending_valuation = TRUE THEN 'Pendiente' ELSE 'Completa' END AS valuation_status";
        }

        $sql = "SELECT mv.created_at AS date, mv.movement_number, w.name AS warehouse, mv.document_number,
                       supplier.name AS supplier, m.code AS material_code, m.name AS material, c.name AS category,
                       i.quantity, u.symbol AS unit, mv.delivered_by_name AS delivered_by,
                       mv.received_by_name AS received_by, mv.received_by_area_name AS area,
                       creator.username AS created_by {$financialSelect}
                  FROM inventory_movements mv
                  JOIN inventory_movement_items i ON i.movement_id = mv.id
                  JOIN materials m ON m.id = i.material_id
                  JOIN categories c ON c.id = m.category_id
                  JOIN measurement_units u ON u.id = m.unit_id
                  JOIN warehouses w ON w.id = mv.warehouse_id
                  JOIN users creator ON creator.id = mv.created_by
                  LEFT JOIN suppliers supplier ON supplier.id = mv.supplier_id
                 WHERE mv.type = ?";
        $params = [$type];
        $this->appendMovementFilters($sql, $params, $filters, 'mv', 'm', false);
        $sql .= ' ORDER BY mv.created_at DESC, mv.id DESC, m.name LIMIT ?';
        $params[] = $limit;

        return [$columns, $this->db()->query($sql, $params)->getResultArray()];
    }

    /** @return array{array<string, string>, list<array<string, mixed>>} */
    private function adjustments(array $filters, int $limit): array
    {
        $columns = [
            'request_number' => 'Solicitud', 'request_type' => 'Tipo', 'status' => 'Estado', 'warehouse' => 'Bodega',
            'reason' => 'Motivo', 'requested_by' => 'Solicitado por', 'requested_at' => 'Fecha solicitud',
            'approved_by' => 'Aprobado por', 'approved_at' => 'Fecha decisión', 'original_movement' => 'Movimiento original',
            'executed_movement' => 'Movimiento ejecutado',
        ];
        $sql = "SELECT r.request_number,
                       CASE r.type WHEN 'ADJUSTMENT' THEN 'Ajuste' ELSE 'Reversión' END AS request_type,
                       CASE r.status WHEN 'PENDING' THEN 'Pendiente' WHEN 'REJECTED' THEN 'Rechazada' ELSE 'Ejecutada' END AS status,
                       w.name AS warehouse, r.reason, r.requested_by_name AS requested_by, r.requested_at,
                       r.approved_by_name AS approved_by, r.approved_at,
                       original.movement_number AS original_movement, executed.movement_number AS executed_movement
                  FROM inventory_requests r
                  JOIN warehouses w ON w.id = r.warehouse_id
                  LEFT JOIN inventory_movements original ON original.id = r.original_movement_id
                  LEFT JOIN inventory_movements executed ON executed.id = r.executed_movement_id
                 WHERE 1 = 1";
        $params = [];
        $this->appendDateRange($sql, $params, $filters, 'r.requested_at');
        if (($filters['warehouse_id'] ?? 0) > 0) {
            $sql .= ' AND r.warehouse_id = ?';
            $params[] = (int) $filters['warehouse_id'];
        }
        if (($filters['user_id'] ?? 0) > 0) {
            $sql .= ' AND (r.requested_by = ? OR r.approved_by = ?)';
            $params[] = (int) $filters['user_id'];
            $params[] = (int) $filters['user_id'];
        }
        if (($filters['movement_type'] ?? '') === 'ADJUSTMENT' || ($filters['movement_type'] ?? '') === 'REVERSAL') {
            $sql .= ' AND r.type = ?';
            $params[] = $filters['movement_type'];
        }
        if (($filters['material_id'] ?? 0) > 0 || ($filters['category_id'] ?? 0) > 0) {
            $sql .= ' AND EXISTS (SELECT 1 FROM inventory_request_items ri JOIN materials fm ON fm.id = ri.material_id WHERE ri.request_id = r.id';
            if (($filters['material_id'] ?? 0) > 0) {
                $sql .= ' AND fm.id = ?';
                $params[] = (int) $filters['material_id'];
            }
            if (($filters['category_id'] ?? 0) > 0) {
                $sql .= ' AND fm.category_id = ?';
                $params[] = (int) $filters['category_id'];
            }
            $sql .= ')';
        }
        $sql .= ' ORDER BY r.requested_at DESC, r.id DESC LIMIT ?';
        $params[] = $limit;

        return [$columns, $this->db()->query($sql, $params)->getResultArray()];
    }

    /** @return array{array<string, string>, list<array<string, mixed>>} */
    private function movementsByUser(array $filters, int $limit): array
    {
        $columns = [
            'user' => 'Usuario', 'total_movements' => 'Movimientos', 'entries' => 'Entradas', 'exits' => 'Salidas',
            'adjustments' => 'Ajustes', 'reversals' => 'Reversiones', 'total_lines' => 'Líneas registradas',
            'last_activity' => 'Última actividad',
        ];
        $sql = "SELECT creator.username AS user, COUNT(DISTINCT mv.id) AS total_movements,
                       COUNT(DISTINCT mv.id) FILTER (WHERE mv.type = 'ENTRY') AS entries,
                       COUNT(DISTINCT mv.id) FILTER (WHERE mv.type = 'EXIT') AS exits,
                       COUNT(DISTINCT mv.id) FILTER (WHERE mv.type = 'ADJUSTMENT') AS adjustments,
                       COUNT(DISTINCT mv.id) FILTER (WHERE mv.type = 'REVERSAL') AS reversals,
                       COUNT(i.id) AS total_lines, MAX(mv.created_at) AS last_activity
                  FROM inventory_movements mv
                  JOIN users creator ON creator.id = mv.created_by
                  JOIN inventory_movement_items i ON i.movement_id = mv.id
                  JOIN materials m ON m.id = i.material_id
                 WHERE 1 = 1";
        $params = [];
        $this->appendMovementFilters($sql, $params, $filters, 'mv', 'm');
        $sql .= ' GROUP BY creator.id, creator.username ORDER BY total_movements DESC, creator.username LIMIT ?';
        $params[] = $limit;

        return [$columns, $this->db()->query($sql, $params)->getResultArray()];
    }

    /** @return array{array<string, string>, list<array<string, mixed>>} */
    private function mostUsedMaterials(array $filters, int $limit): array
    {
        $columns = [
            'code' => 'Código', 'material' => 'Material', 'category' => 'Categoría', 'unit' => 'Unidad',
            'total_quantity' => 'Cantidad utilizada', 'movements' => 'Movimientos', 'last_exit' => 'Última salida',
        ];
        $sql = "SELECT m.code, m.name AS material, c.name AS category, u.symbol AS unit,
                       SUM(i.quantity) AS total_quantity, COUNT(DISTINCT mv.id) AS movements,
                       MAX(mv.created_at) AS last_exit
                  FROM inventory_movement_items i
                  JOIN inventory_movements mv ON mv.id = i.movement_id
                  JOIN materials m ON m.id = i.material_id
                  JOIN categories c ON c.id = m.category_id
                  JOIN measurement_units u ON u.id = m.unit_id
                 WHERE i.direction = -1 AND mv.type = 'EXIT'";
        $params = [];
        $this->appendMovementFilters($sql, $params, $filters, 'mv', 'm');
        $sql .= ' GROUP BY m.id, m.code, m.name, c.name, u.symbol ORDER BY total_quantity DESC, m.name LIMIT ?';
        $params[] = $limit;

        return [$columns, $this->db()->query($sql, $params)->getResultArray()];
    }

    /** @return array{array<string, string>, list<array<string, mixed>>} */
    private function administrativeAudit(array $filters, bool $sensitive, int $limit): array
    {
        $columns = [
            'date' => 'Fecha', 'user' => 'Usuario', 'module' => 'Módulo', 'action' => 'Acción',
            'result' => 'Resultado', 'method' => 'Método', 'path' => 'Ruta', 'record' => 'Registro', 'user_agent' => 'Agente',
        ];
        $ipSelect = '';
        if ($sensitive) {
            $columns += ['ip_address' => 'Dirección IP'];
            $ipSelect = ', ip_address';
        }
        $sql = "SELECT occurred_at AS date, COALESCE(actor_name, 'Sistema o visitante') AS user,
                       module, action, result, http_method AS method, request_path AS path,
                       CASE WHEN entity_id IS NULL THEN COALESCE(entity_type, '—') ELSE COALESCE(entity_type, 'registro') || ' #' || entity_id END AS record,
                       user_agent {$ipSelect}
                  FROM audit_events WHERE 1 = 1";
        $params = [];
        $this->appendDateRange($sql, $params, $filters, 'occurred_at');
        if (($filters['user_id'] ?? 0) > 0) {
            $sql .= ' AND actor_user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (($filters['audit_module'] ?? '') !== '') {
            $sql .= ' AND module = ?';
            $params[] = $filters['audit_module'];
        }
        if (($filters['audit_result'] ?? '') !== '') {
            $sql .= ' AND result = ?';
            $params[] = $filters['audit_result'];
        }
        if (($filters['action'] ?? '') !== '') {
            $sql .= ' AND action ILIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }
        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT ?';
        $params[] = $limit;

        return [$columns, $this->db()->query($sql, $params)->getResultArray()];
    }

    /** @param list<mixed> $params */
    private function appendCatalogFilters(string &$sql, array &$params, array $filters, string $materialAlias, string $warehouseAlias): void
    {
        if (($filters['material_id'] ?? 0) > 0) {
            $sql .= " AND {$materialAlias}.id = ?";
            $params[] = (int) $filters['material_id'];
        }
        if (($filters['category_id'] ?? 0) > 0) {
            $sql .= " AND {$materialAlias}.category_id = ?";
            $params[] = (int) $filters['category_id'];
        }
        if (($filters['warehouse_id'] ?? 0) > 0) {
            $sql .= " AND {$warehouseAlias}.id = ?";
            $params[] = (int) $filters['warehouse_id'];
        }
    }

    /** @param list<mixed> $params */
    private function appendMovementFilters(
        string &$sql,
        array &$params,
        array $filters,
        string $movementAlias,
        string $materialAlias,
        bool $includeMovementType = true,
    ): void {
        $this->appendDateRange($sql, $params, $filters, $movementAlias . '.created_at');
        if (($filters['warehouse_id'] ?? 0) > 0) {
            $sql .= " AND {$movementAlias}.warehouse_id = ?";
            $params[] = (int) $filters['warehouse_id'];
        }
        if (($filters['material_id'] ?? 0) > 0) {
            $sql .= " AND {$materialAlias}.id = ?";
            $params[] = (int) $filters['material_id'];
        }
        if (($filters['category_id'] ?? 0) > 0) {
            $sql .= " AND {$materialAlias}.category_id = ?";
            $params[] = (int) $filters['category_id'];
        }
        if (($filters['user_id'] ?? 0) > 0) {
            $sql .= " AND {$movementAlias}.created_by = ?";
            $params[] = (int) $filters['user_id'];
        }
        if ($includeMovementType && ($filters['movement_type'] ?? '') !== '') {
            $sql .= " AND {$movementAlias}.type = ?";
            $params[] = $filters['movement_type'];
        }
    }

    /** @param list<mixed> $params */
    private function appendDateRange(string &$sql, array &$params, array $filters, string $column): void
    {
        if (($filters['from'] ?? '') !== '') {
            $sql .= " AND {$column} >= ?";
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $sql .= " AND {$column} < ?";
            $params[] = date('Y-m-d', strtotime($filters['to'] . ' +1 day')) . ' 00:00:00';
        }
    }

    private function db(): BaseConnection
    {
        return $this->database ?? db_connect();
    }
}
