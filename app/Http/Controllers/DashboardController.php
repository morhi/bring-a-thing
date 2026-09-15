<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard: groups, lists, and claimed items the user owns, is a member of, or has claimed on.
     *
     * Groups/lists/claims arrive in Phase 2/3; the page renders empty states until then.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard');
    }
}
