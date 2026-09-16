<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Show the public marketing landing page.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Welcome');
    }
}
