<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

final class HealthController extends BaseController
{
    public function index(): ResponseInterface
    {
        return $this->response->setJSON(['status' => 'ok']);
    }
}
