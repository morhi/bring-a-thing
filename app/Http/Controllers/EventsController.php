<?php

namespace App\Http\Controllers;

use App\Data\EventsFiltersData;
use App\Data\EventsIndexData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventsController extends Controller
{
    /**
     * Show the page "All events" (F-32), filtered by "My events only" and upcoming or all.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'mine' => ['sometimes', 'boolean'],
            'all' => ['sometimes', 'boolean'],
        ]);

        $filters = new EventsFiltersData(
            mine: $request->boolean('mine'),
            all: $request->boolean('all'),
        );

        return Inertia::render('Events/Index', EventsIndexData::forUser($request->user(), $filters)->toArray());
    }
}
