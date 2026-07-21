<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class DashboardService
{
    public function __construct(private readonly ?BaseConnection $database = null)
    {
    }

    /** @return array<string, int> */
    public function indicators(): array
    {
        $db = $this->db();
        $row = $db->query(<<<'SQL'
SELECT
    (SELECT COUNT(*) FROM materials WHERE active = TRUE) AS active_materials,
    (SELECT COUNT(*)
       FROM materials m
       CROSS JOIN warehouses w
       LEFT JOIN inventory_stocks s ON s.material_id = m.id AND s.warehouse_id = w.id
      WHERE m.active = TRUE AND w.active = TRUE AND COALESCE(s.quantity, 0) <= m.minimum_stock) AS low_stock,
    (SELECT COUNT(*) FROM inventory_requests WHERE status = 'PENDING') AS pending_requests,
    (SELECT COUNT(*) FROM inventory_movements WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL '30 days') AS recent_movements,
    (SELECT COUNT(*)
       FROM inventory_movement_items i
       JOIN inventory_movements mv ON mv.id = i.movement_id
       LEFT JOIN (
           SELECT movement_item_id, SUM(quantity_basis) AS allocated
             FROM inventory_valuation_events
            WHERE event_type = 'ALLOCATION'
            GROUP BY movement_item_id
       ) v ON v.movement_item_id = i.id
      WHERE i.direction = 1
        AND i.pending_valuation = TRUE
        AND i.quantity > COALESCE(v.allocated, 0)) AS pending_valuations
SQL)->getRowArray() ?? [];

        return [
            'activeMaterials'   => (int) ($row['active_materials'] ?? 0),
            'lowStock'          => (int) ($row['low_stock'] ?? 0),
            'pendingRequests'   => (int) ($row['pending_requests'] ?? 0),
            'recentMovements'   => (int) ($row['recent_movements'] ?? 0),
            'pendingValuations' => (int) ($row['pending_valuations'] ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function lowStock(?int $warehouseId = null, int $limit = 100): array
    {
        $sql = <<<'SQL'
SELECT m.id AS material_id, m.code, m.name, m.minimum_stock,
       u.symbol AS unit_symbol, w.id AS warehouse_id, w.name AS warehouse_name,
       COALESCE(s.quantity, 0) AS quantity,
       n.last_notified_at
  FROM materials m
  JOIN measurement_units u ON u.id = m.unit_id
 CROSS JOIN warehouses w
  LEFT JOIN inventory_stocks s ON s.material_id = m.id AND s.warehouse_id = w.id
  LEFT JOIN stock_alert_notification_states n ON n.material_id = m.id AND n.warehouse_id = w.id
 WHERE m.active = TRUE
   AND w.active = TRUE
   AND COALESCE(s.quantity, 0) <= m.minimum_stock
SQL;
        $params = [];
        if ($warehouseId !== null && $warehouseId > 0) {
            $sql .= ' AND w.id = ?';
            $params[] = $warehouseId;
        }
        $sql .= ' ORDER BY (m.minimum_stock - COALESCE(s.quantity, 0)) DESC, w.name, m.name LIMIT ?';
        $params[] = $limit;

        return $this->db()->query($sql, $params)->getResultArray();
    }

    /** @return list<array<string, mixed>> */
    public function recentMovements(int $limit = 8): array
    {
        return $this->db()->query(<<<'SQL'
SELECT mv.id, mv.movement_number, mv.type, mv.created_at, w.name AS warehouse_name,
       u.username AS created_by_name
  FROM inventory_movements mv
  JOIN warehouses w ON w.id = mv.warehouse_id
  JOIN users u ON u.id = mv.created_by
 ORDER BY mv.id DESC
 LIMIT ?
SQL, [$limit])->getResultArray();
    }

    private function db(): BaseConnection
    {
        return $this->database ?? db_connect();
    }
}
