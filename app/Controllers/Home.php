<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

final class Home extends BaseController
{
    public function index(): RedirectResponse
    {
        return redirect()->route(auth()->loggedIn() ? 'dashboard' : 'login');
    }
}
