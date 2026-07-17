<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);
$routes->get('/', 'Home::index');
$routes->get('health', 'HealthController::index');
