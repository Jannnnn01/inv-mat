<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

abstract class BaseCatalogModel extends Model
{
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $useSoftDeletes = false;
    protected $protectFields = true;
}
