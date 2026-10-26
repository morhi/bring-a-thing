<?php

namespace App\Http\Controllers;

use App\Data\WelcomeData;
use App\Enums\LoginLinkError;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Show the start page with the login and sign-up form (F-01, F-24).
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Welcome', [
            'welcome' => WelcomeData::make(
                LoginLinkError::tryFrom((string) $request->session()->get('login_link_error')),
            ),
        ]);
    }
}
