<?php

declare(strict_types=1);

namespace App\Models;

final class SupplierModel extends BaseCatalogModel
{
    protected $table = 'suppliers';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'name', 'document_type', 'document_number', 'contact_name',
        'email', 'phone', 'address', 'active', 'created_by', 'updated_by',
    ];
}
