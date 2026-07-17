<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    public string $defaultGroup = 'viewer';

    public array $groups = [
        'admin' => [
            'title'       => 'Administrador',
            'description' => 'Administra seguridad, usuarios, inventario y configuracion.',
        ],
        'warehouse' => [
            'title'       => 'Encargado de bodega',
            'description' => 'Gestiona catalogos y movimientos operativos autorizados.',
        ],
        'viewer' => [
            'title'       => 'Usuario de consulta',
            'description' => 'Consulta existencias, movimientos y reportes permitidos.',
        ],
    ];

    public array $permissions = [
        'dashboard.view'                     => 'Consultar el dashboard.',
        'stock.view'                         => 'Consultar existencias y alertas.',
        'materials.view'                     => 'Consultar materiales.',
        'materials.create'                   => 'Crear materiales.',
        'materials.update'                   => 'Editar materiales.',
        'materials.deactivate'               => 'Desactivar materiales.',
        'categories.view'                    => 'Consultar categorias.',
        'categories.create'                  => 'Crear categorias.',
        'categories.update'                  => 'Editar categorias.',
        'categories.deactivate'              => 'Desactivar categorias.',
        'suppliers.view'                     => 'Consultar proveedores.',
        'suppliers.create'                   => 'Crear proveedores.',
        'suppliers.update'                   => 'Editar proveedores.',
        'suppliers.deactivate'               => 'Desactivar proveedores.',
        'inventory.entries.create'           => 'Registrar entradas.',
        'inventory.exits.create'             => 'Registrar salidas.',
        'inventory.movements.view'           => 'Consultar movimientos.',
        'inventory.adjustments.request'      => 'Solicitar ajustes.',
        'inventory.adjustments.approve'      => 'Aprobar ajustes ajenos.',
        'inventory.reversals.request'        => 'Solicitar reversiones.',
        'inventory.reversals.approve'        => 'Aprobar reversiones ajenas.',
        'reports.view'                       => 'Consultar reportes.',
        'reports.export'                     => 'Exportar reportes.',
        'financial.view'                     => 'Consultar costos y valoracion.',
        'financial.manage'                   => 'Completar o corregir valoraciones.',
        'users.manage'                       => 'Crear, editar y desactivar usuarios.',
        'roles.assign'                       => 'Asignar el rol principal.',
        'warehouses.manage'                  => 'Gestionar bodegas.',
        'files.upload'                       => 'Cargar archivos permitidos.',
        'files.download'                     => 'Descargar archivos autorizados.',
        'audit.view'                         => 'Consultar auditoria.',
        'audit.sensitive'                    => 'Consultar IP y datos protegidos de auditoria.',
        'security.manage'                    => 'Modificar configuracion de seguridad.',
    ];

    public array $matrix = [
        'admin' => [
            'dashboard.*', 'stock.*', 'materials.*', 'categories.*', 'suppliers.*',
            'inventory.*', 'reports.*', 'financial.*', 'users.*', 'roles.*',
            'warehouses.*', 'files.*', 'audit.*', 'security.*',
        ],
        'warehouse' => [
            'dashboard.view', 'stock.view',
            'materials.view', 'materials.create', 'materials.update',
            'categories.view', 'categories.create', 'categories.update',
            'suppliers.view', 'suppliers.create', 'suppliers.update',
            'inventory.entries.create', 'inventory.exits.create',
            'inventory.movements.view', 'inventory.adjustments.request',
            'inventory.reversals.request', 'reports.view', 'reports.export',
            'files.upload', 'files.download',
        ],
        'viewer' => [
            'dashboard.view', 'stock.view', 'materials.view', 'categories.view',
            'suppliers.view', 'inventory.movements.view', 'reports.view',
            'reports.export', 'files.download',
        ],
    ];
}
