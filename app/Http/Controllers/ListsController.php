<?php

namespace App\Http\Controllers;

use App\Data\ListsFiltersData;
use App\Data\ListsIndexData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListsController extends Controller
{
    /**
     * Show the page "All lists" (F-28), filtered by "My lists only", "Tasks only" and active or all.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'mine' => ['sometimes', 'boolean'],
            'tasks' => ['sometimes', 'boolean'],
            'all' => ['sometimes', 'boolean'],
        ]);

        $filters = new ListsFiltersData(
            mine: $request->boolean('mine'),
            tasks: $request->boolean('tasks'),
            all: $request->boolean('all'),
        );

        return Inertia::render('Rosters/Index', ListsIndexData::forUser($request->user(), $filters)->toArray());
    }
}
