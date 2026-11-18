<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialHintController extends Controller
{
    /**
     * Dismiss the hint to set a password or passkey for the signed-in account (F-02).
     *
     * Answers with JSON instead of a redirect: the banner hides itself and the
     * dismissal gives its own feedback, so no toast is flashed.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->forceFill(['credential_hint_dismissed_at' => now()])->save();

        return response()->json(['dismissed' => true]);
    }
}
