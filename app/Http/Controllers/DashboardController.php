<?php

namespace App\Http\Controllers;

use App\Data\DashboardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard (F-22): what waits for the person, upcoming events and claims, groups and standalone lists.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', DashboardData::forUser($request->user())->toArray());
    }
}
