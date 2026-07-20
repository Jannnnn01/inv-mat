<?php

namespace App\Controllers;

final class DashboardController extends BaseController
{
    public function index(): string
    {
        return view('dashboard/index');
    }
}
