<?php

declare(strict_types=1);

namespace App\Models;

final class CategoryModel extends BaseCatalogModel
{
    protected $table = 'categories';
    protected $primaryKey = 'id';
    protected $allowedFields = ['code', 'name', 'description', 'active', 'created_by', 'updated_by'];
}
