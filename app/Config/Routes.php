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
    'filter' => 'session',
]);
$routes->post('mi-cuenta/contrasena', 'Account\PasswordController::update', [
    'filter' => 'session',
]);

$routes->get('dashboard', 'DashboardController::index', [
    'as'     => 'dashboard',
    'filter' => ['session', 'force-reset', 'permission:dashboard.view'],
]);
