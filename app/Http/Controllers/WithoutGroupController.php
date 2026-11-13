<?php

namespace App\Http\Controllers;

use App\Data\WithoutGroupData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithoutGroupController extends Controller
{
    /**
     * Show the virtual group "Without groups" (F-29): grant-reached events and polls and groupless lists.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('WithoutGroup/Show', WithoutGroupData::forUser($request->user())->toArray());
    }
}
