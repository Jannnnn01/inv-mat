<?php

declare(strict_types=1);

namespace App\Models;

final class RecipientModel extends BaseCatalogModel
{
    protected $table = 'recipients';
    protected $allowedFields = [
        'name', 'document_type', 'document_number', 'address', 'route', 'contact_name',
        'email', 'phone', 'active', 'created_by', 'updated_by',
    ];
}
