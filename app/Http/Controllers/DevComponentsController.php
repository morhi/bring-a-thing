<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DevComponentsController extends Controller
{
    /**
     * Show the dev-only component catalogue (local environment only).
     */
    public function __invoke(): Response
    {
        return Inertia::render('Dev/Components');
    }
}
