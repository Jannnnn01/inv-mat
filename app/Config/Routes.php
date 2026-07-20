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
