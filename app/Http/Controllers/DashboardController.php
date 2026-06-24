<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the authenticated dashboard.
     */
    public function index(Request $request, DashboardService $dashboardService): View
    {
        return view('dashboard', [
            'dashboardData' => $dashboardService->forUser($request->user()),
        ]);
    }
}
