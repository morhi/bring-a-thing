<?php

namespace App\Http\Controllers;

use App\Data\OperatorData;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    /**
     * Show the privacy policy (F-33).
     */
    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy', ['operator' => OperatorData::fromConfig()]);
    }

    /**
     * Show the legal notice (F-33).
     */
    public function notice(): Response
    {
        return Inertia::render('Legal/Notice', ['operator' => OperatorData::fromConfig()]);
    }
}
