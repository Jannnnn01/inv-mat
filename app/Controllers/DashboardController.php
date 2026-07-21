<?php

namespace App\Controllers;

use App\Services\DashboardService;

final class DashboardController extends BaseController
{
    public function index(): string
    {
        $service = new DashboardService();

        return view('dashboard/index', [
            'indicators'      => $service->indicators(),
            'lowStock'        => $service->lowStock(null, 5),
            'recentMovements' => $service->recentMovements(),
            'showFinancial'   => auth()->user()?->can('financial.view') ?? false,
        ]);
    }
}
