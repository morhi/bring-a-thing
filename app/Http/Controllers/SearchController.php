<?php

namespace App\Http\Controllers;

use App\Data\SearchData;
use App\Http\Requests\Search\SearchRequest;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    /**
     * Find the groups, polls, events and lists the signed-in account may see for the command palette (F-25).
     */
    public function __invoke(SearchRequest $request): JsonResponse
    {
        return response()->json(SearchData::forUser($request->user(), $request->string('q')->trim()->value()));
    }
}
