<?php

namespace App\Http\Controllers;

use App\Data\PollsFiltersData;
use App\Data\PollsIndexData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PollsController extends Controller
{
    /**
     * Show the page "All polls" (F-31), filtered by "Unanswered", "My polls only" and open or all.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'unanswered' => ['sometimes', 'boolean'],
            'mine' => ['sometimes', 'boolean'],
            'all' => ['sometimes', 'boolean'],
        ]);

        $filters = new PollsFiltersData(
            unanswered: $request->boolean('unanswered'),
            mine: $request->boolean('mine'),
            all: $request->boolean('all'),
        );

        return Inertia::render('Polls/Index', PollsIndexData::forUser($request->user(), $filters)->toArray());
    }
}
