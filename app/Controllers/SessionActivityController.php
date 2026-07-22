<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

final class SessionActivityController extends BaseController
{
    public function touch(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(204)
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
