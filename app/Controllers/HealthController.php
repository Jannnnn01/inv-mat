<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Health;
use Throwable;

final class HealthController extends BaseController
{
    public function index(): ResponseInterface
    {
        if (config(Health::class)->databaseCheck) {
            try {
                db_connect()->query('SELECT 1')->getRow();
            } catch (Throwable $exception) {
                log_message('error', 'Falló la comprobación de disponibilidad de la base de datos: {message}', [
                    'message' => $exception->getMessage(),
                ]);

                return $this->response->setStatusCode(503)->setJSON(['status' => 'unavailable']);
            }
        }

        return $this->response->setJSON(['status' => 'ok']);
    }
}
