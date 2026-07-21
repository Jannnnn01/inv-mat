<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

final class AuditEventModel extends Model
{
    protected $table = 'audit_events';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $useTimestamps = false;
    protected $allowedFields = [];

    /**
     * @param array{date_from?: string, date_to?: string, actor_user_id?: int, module?: string, action?: string, result?: string} $filters
     */
    public function applyFilters(array $filters): self
    {
        if (($filters['date_from'] ?? '') !== '') {
            $this->where('occurred_at >=', $filters['date_from'] . ' 00:00:00');
        }

        if (($filters['date_to'] ?? '') !== '') {
            $nextDay = date('Y-m-d', strtotime($filters['date_to'] . ' +1 day'));
            $this->where('occurred_at <', $nextDay . ' 00:00:00');
        }

        if (($filters['actor_user_id'] ?? 0) > 0) {
            $this->where('actor_user_id', $filters['actor_user_id']);
        }

        if (($filters['module'] ?? '') !== '') {
            $this->where('module', $filters['module']);
        }

        if (($filters['action'] ?? '') !== '') {
            $this->like('action', $filters['action'], 'both', true, true);
        }

        if (($filters['result'] ?? '') !== '') {
            $this->where('result', $filters['result']);
        }

        return $this;
    }
}
