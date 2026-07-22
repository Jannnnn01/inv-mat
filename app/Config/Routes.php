<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('/', 'Home::index');
$routes->get('health', 'HealthController::index');

$routes->get('login', '\\CodeIgniter\\Shield\\Controllers\\LoginController::loginView', ['as' => 'login']);
$routes->post('login', '\\CodeIgniter\\Shield\\Controllers\\LoginController::loginAction');
$routes->get('auth/a/show', '\\CodeIgniter\\Shield\\Controllers\\ActionController::show', ['as' => 'auth-action-show']);
$routes->post('auth/a/handle', '\\CodeIgniter\\Shield\\Controllers\\ActionController::handle', ['as' => 'auth-action-handle']);
$routes->post('auth/a/verify', '\\CodeIgniter\\Shield\\Controllers\\ActionController::verify', ['as' => 'auth-action-verify']);

$routes->get('login/magic-link', 'Auth\RecoveryController::loginView', ['as' => 'magic-link']);
$routes->post('login/magic-link', 'Auth\RecoveryController::loginAction');
$routes->get('login/verify-magic-link', 'Auth\RecoveryController::verify', ['as' => 'verify-magic-link']);

$routes->post('logout', '\CodeIgniter\Shield\Controllers\LoginController::logoutAction', [
    'as'     => 'logout',
    'filter' => 'session',
]);

$routes->get('sesion/actividad', 'SessionActivityController::touch', [
    'as'     => 'session-activity',
    'filter' => ['session', 'active-user'],
]);

$routes->get('mi-cuenta/contrasena', 'Account\PasswordController::edit', [
    'as'     => 'account-password',
    'filter' => ['session', 'active-user'],
]);
$routes->post('mi-cuenta/contrasena', 'Account\PasswordController::update', [
    'filter' => ['session', 'active-user'],
]);

$routes->get('dashboard', 'DashboardController::index', [
    'as'     => 'dashboard',
    'filter' => ['session', 'active-user', 'force-reset', 'permission:dashboard.view'],
]);

$routes->group('admin', [
    'filter' => ['session', 'active-user', 'force-reset', 'permission:users.manage'],
], static function (RouteCollection $routes): void {
    $routes->get('usuarios', 'Admin\UserController::index', ['as' => 'admin-users']);
    $routes->get('usuarios/nuevo', 'Admin\UserController::new', ['as' => 'admin-users-new']);
    $routes->post('usuarios', 'Admin\UserController::create', [
        'as'     => 'admin-users-create',
        'filter' => 'permission:roles.assign',
    ]);
    $routes->post('usuarios/(:num)/estado', 'Admin\UserController::toggleStatus/$1', ['as' => 'admin-users-status']);
    $routes->post('usuarios/(:num)/rol', 'Admin\UserController::updateRole/$1', [
        'as'     => 'admin-users-role',
        'filter' => 'permission:roles.assign',
    ]);
    $routes->post('usuarios/(:num)/invitacion', 'Admin\UserController::resendInvitation/$1', ['as' => 'admin-users-invite']);
});

$routes->group('admin/auditoria', [
    'filter' => ['session', 'active-user', 'force-reset', 'permission:audit.view'],
], static function (RouteCollection $routes): void {
    $routes->get('', 'Admin\AuditController::index', ['as' => 'admin-audit']);
    $routes->get('(:num)', 'Admin\AuditController::show/$1', ['as' => 'admin-audit-show']);
});

$catalogBaseFilters = ['session', 'active-user', 'force-reset'];

$routes->group('catalogos', ['filter' => $catalogBaseFilters], static function (RouteCollection $routes): void {
    $routes->get('bodegas', 'Catalogs\WarehouseController::index', ['as' => 'warehouses', 'filter' => 'permission:warehouses.manage']);
    $routes->get('bodegas/nueva', 'Catalogs\WarehouseController::new', ['as' => 'warehouses-new', 'filter' => 'permission:warehouses.manage']);
    $routes->post('bodegas', 'Catalogs\WarehouseController::create', ['as' => 'warehouses-create', 'filter' => 'permission:warehouses.manage']);
    $routes->get('bodegas/(:num)/editar', 'Catalogs\WarehouseController::edit/$1', ['as' => 'warehouses-edit', 'filter' => 'permission:warehouses.manage']);
    $routes->post('bodegas/(:num)', 'Catalogs\WarehouseController::update/$1', ['as' => 'warehouses-update', 'filter' => 'permission:warehouses.manage']);
    $routes->post('bodegas/(:num)/estado', 'Catalogs\WarehouseController::toggle/$1', ['as' => 'warehouses-toggle', 'filter' => 'permission:warehouses.manage']);

    $routes->get('unidades', 'Catalogs\MeasurementUnitController::index', ['as' => 'units', 'filter' => 'permission:units.view']);
    $routes->get('unidades/nueva', 'Catalogs\MeasurementUnitController::new', ['as' => 'units-new', 'filter' => 'permission:units.manage']);
    $routes->post('unidades', 'Catalogs\MeasurementUnitController::create', ['as' => 'units-create', 'filter' => 'permission:units.manage']);
    $routes->get('unidades/(:num)/editar', 'Catalogs\MeasurementUnitController::edit/$1', ['as' => 'units-edit', 'filter' => 'permission:units.manage']);
    $routes->post('unidades/(:num)', 'Catalogs\MeasurementUnitController::update/$1', ['as' => 'units-update', 'filter' => 'permission:units.manage']);
    $routes->post('unidades/(:num)/estado', 'Catalogs\MeasurementUnitController::toggle/$1', ['as' => 'units-toggle', 'filter' => 'permission:units.manage']);

    $routes->get('categorias', 'Catalogs\CategoryController::index', ['as' => 'categories', 'filter' => 'permission:categories.view']);
    $routes->get('categorias/nueva', 'Catalogs\CategoryController::new', ['as' => 'categories-new', 'filter' => 'permission:categories.create']);
    $routes->post('categorias', 'Catalogs\CategoryController::create', ['as' => 'categories-create', 'filter' => 'permission:categories.create']);
    $routes->get('categorias/(:num)/editar', 'Catalogs\CategoryController::edit/$1', ['as' => 'categories-edit', 'filter' => 'permission:categories.update']);
    $routes->post('categorias/(:num)', 'Catalogs\CategoryController::update/$1', ['as' => 'categories-update', 'filter' => 'permission:categories.update']);
    $routes->post('categorias/(:num)/estado', 'Catalogs\CategoryController::toggle/$1', ['as' => 'categories-toggle', 'filter' => 'permission:categories.deactivate']);

    $routes->get('proveedores', 'Catalogs\SupplierController::index', ['as' => 'suppliers', 'filter' => 'permission:suppliers.view']);
    $routes->get('proveedores/nuevo', 'Catalogs\SupplierController::new', ['as' => 'suppliers-new', 'filter' => 'permission:suppliers.create']);
    $routes->post('proveedores', 'Catalogs\SupplierController::create', ['as' => 'suppliers-create', 'filter' => 'permission:suppliers.create']);
    $routes->get('proveedores/(:num)/editar', 'Catalogs\SupplierController::edit/$1', ['as' => 'suppliers-edit', 'filter' => 'permission:suppliers.update']);
    $routes->post('proveedores/(:num)', 'Catalogs\SupplierController::update/$1', ['as' => 'suppliers-update', 'filter' => 'permission:suppliers.update']);
    $routes->post('proveedores/(:num)/estado', 'Catalogs\SupplierController::toggle/$1', ['as' => 'suppliers-toggle', 'filter' => 'permission:suppliers.deactivate']);

    $routes->get('destinatarios', 'Catalogs\RecipientController::index', ['as' => 'recipients', 'filter' => 'permission:recipients.view']);
    $routes->get('destinatarios/nuevo', 'Catalogs\RecipientController::new', ['as' => 'recipients-new', 'filter' => 'permission:recipients.manage']);
    $routes->post('destinatarios', 'Catalogs\RecipientController::create', ['as' => 'recipients-create', 'filter' => 'permission:recipients.manage']);
    $routes->get('destinatarios/(:num)/editar', 'Catalogs\RecipientController::edit/$1', ['as' => 'recipients-edit', 'filter' => 'permission:recipients.manage']);
    $routes->post('destinatarios/(:num)', 'Catalogs\RecipientController::update/$1', ['as' => 'recipients-update', 'filter' => 'permission:recipients.manage']);
    $routes->post('destinatarios/(:num)/estado', 'Catalogs\RecipientController::toggle/$1', ['as' => 'recipients-toggle', 'filter' => 'permission:recipients.manage']);

    $routes->get('materiales', 'Catalogs\MaterialController::index', ['as' => 'materials', 'filter' => 'permission:materials.view']);
    $routes->get('materiales/nuevo', 'Catalogs\MaterialController::new', ['as' => 'materials-new', 'filter' => 'permission:materials.create']);
    $routes->post('materiales', 'Catalogs\MaterialController::create', ['as' => 'materials-create', 'filter' => 'permission:materials.create']);
    $routes->get('materiales/(:num)/editar', 'Catalogs\MaterialController::edit/$1', ['as' => 'materials-edit', 'filter' => 'permission:materials.update']);
    $routes->post('materiales/(:num)', 'Catalogs\MaterialController::update/$1', ['as' => 'materials-update', 'filter' => 'permission:materials.update']);
    $routes->post('materiales/(:num)/estado', 'Catalogs\MaterialController::toggle/$1', ['as' => 'materials-toggle', 'filter' => 'permission:materials.deactivate']);
});

$routes->group('inventario', ['filter' => $catalogBaseFilters], static function (RouteCollection $routes): void {
    $routes->get('existencias', 'Inventory\MovementController::stocks', ['as' => 'inventory-stocks', 'filter' => 'permission:stock.view']);
    $routes->get('alertas', 'Inventory\AlertController::index', ['as' => 'inventory-alerts', 'filter' => 'permission:stock.view']);
    $routes->get('valoraciones', 'Inventory\ValuationController::index', ['as' => 'inventory-valuations', 'filter' => 'permission:financial.view']);
    $routes->post('valoraciones/detalles/(:num)', 'Inventory\ValuationController::complete/$1', ['as' => 'inventory-valuation-complete', 'filter' => 'permission:financial.manage']);
    $routes->post('valoraciones/(:num)/correccion', 'Inventory\ValuationController::correct/$1', ['as' => 'inventory-valuation-correct', 'filter' => 'permission:financial.manage']);
    $routes->get('movimientos', 'Inventory\MovementController::index', ['as' => 'inventory-movements', 'filter' => 'permission:inventory.movements.view']);
    $routes->get('movimientos/entrada/nueva', 'Inventory\MovementController::newEntry', ['as' => 'inventory-entry-new', 'filter' => 'permission:inventory.entries.create']);
    $routes->post('movimientos/entrada', 'Inventory\MovementController::createEntry', ['as' => 'inventory-entry-create', 'filter' => 'permission:inventory.entries.create']);
    $routes->get('movimientos/salida/nueva', 'Inventory\MovementController::newExit', ['as' => 'inventory-exit-new', 'filter' => 'permission:inventory.exits.create']);
    $routes->post('movimientos/salida', 'Inventory\MovementController::createExit', ['as' => 'inventory-exit-create', 'filter' => 'permission:inventory.exits.create']);
    $routes->get('movimientos/(:num)', 'Inventory\MovementController::show/$1', ['as' => 'inventory-movement-show', 'filter' => 'permission:inventory.movements.view']);
    $routes->post('movimientos/(:num)/archivos', 'Inventory\AttachmentController::upload/$1', ['as' => 'inventory-attachment-upload', 'filter' => 'permission:files.upload']);
    $routes->get('archivos/(:num)/descargar', 'Inventory\AttachmentController::download/$1', ['as' => 'inventory-attachment-download', 'filter' => 'permission:files.download']);
    $routes->post('archivos/(:num)/archivar', 'Inventory\AttachmentController::archive/$1', ['as' => 'inventory-attachment-archive', 'filter' => 'permission:files.archive']);
    $routes->get('despachos/pendientes', 'Inventory\DispatchController::pending', ['as' => 'inventory-dispatch-pending', 'filter' => 'permission:inventory.movements.view']);
    $routes->get('despachos/(:num)/entrega', 'Inventory\DispatchController::newDelivery/$1', ['as' => 'inventory-dispatch-delivery-new', 'filter' => 'permission:inventory.exits.create']);
    $routes->post('despachos/(:num)/entrega', 'Inventory\DispatchController::createDelivery/$1', ['as' => 'inventory-dispatch-delivery-create', 'filter' => 'permission:inventory.exits.create']);
    $routes->get('despachos/(:num)/guia.pdf', 'Inventory\DispatchController::pdf/$1', ['as' => 'inventory-dispatch-pdf', 'filter' => 'permission:inventory.movements.view']);
    $routes->get('requisiciones', 'Inventory\DispatchRequestController::index', ['as' => 'dispatch-requests', 'filter' => 'permission:inventory.dispatch_requests.create']);
    $routes->get('requisiciones/nueva', 'Inventory\DispatchRequestController::new', ['as' => 'dispatch-requests-new', 'filter' => 'permission:inventory.dispatch_requests.create']);
    $routes->post('requisiciones', 'Inventory\DispatchRequestController::create', ['as' => 'dispatch-requests-create', 'filter' => 'permission:inventory.dispatch_requests.create']);
    $routes->get('requisiciones/(:num)', 'Inventory\DispatchRequestController::show/$1', ['as' => 'dispatch-request-show', 'filter' => 'permission:inventory.dispatch_requests.create']);
    $routes->post('requisiciones/(:num)/decision', 'Inventory\DispatchRequestController::decide/$1', ['as' => 'dispatch-request-decide', 'filter' => 'permission:inventory.dispatch_requests.approve']);
    $routes->post('movimientos/(:num)/solicitar-reversion', 'Inventory\RequestController::createReversal/$1', ['as' => 'inventory-reversal-request', 'filter' => 'permission:inventory.reversals.request']);

    $routes->get('solicitudes', 'Inventory\RequestController::index', ['as' => 'inventory-requests', 'filter' => 'permission:inventory.adjustments.request']);
    $routes->get('solicitudes/ajuste/nueva', 'Inventory\RequestController::newAdjustment', ['as' => 'inventory-adjustment-new', 'filter' => 'permission:inventory.adjustments.request']);
    $routes->post('solicitudes/ajuste', 'Inventory\RequestController::createAdjustment', ['as' => 'inventory-adjustment-create', 'filter' => 'permission:inventory.adjustments.request']);
    $routes->post('solicitudes/(:num)/decision', 'Inventory\RequestController::decide/$1', ['as' => 'inventory-request-decide', 'filter' => 'permission:inventory.movements.view']);
});

$routes->group('reportes', ['filter' => $catalogBaseFilters], static function (RouteCollection $routes): void {
    $routes->get('', 'ReportController::index', ['as' => 'reports', 'filter' => 'permission:reports.view']);
    $routes->get('(:segment)/csv', 'ReportController::csv/$1', ['as' => 'reports-csv', 'filter' => 'permission:reports.export']);
    $routes->get('(:segment)/pdf', 'ReportController::pdf/$1', ['as' => 'reports-pdf', 'filter' => 'permission:reports.export']);
});
