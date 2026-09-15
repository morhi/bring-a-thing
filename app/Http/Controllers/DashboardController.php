<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard: groups, standalone rosters, and claimed items the user owns, is a member of, or has claimed on.
     *
     * Claimed items arrive in Phase 4; the page renders an empty state for that until then.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'groups' => $request->user()->groups()->get(),
            'rosters' => $request->user()->standaloneRosters()->latest()->get(),
        ]);
    }
}
