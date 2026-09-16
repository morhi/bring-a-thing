<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Sharing\CompletePendingSharedClaim;
use App\Actions\Sharing\StorePendingSharedClaim;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginWithPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Log in a user with their password.
     *
     * A pending claim from a shared list link, started before login, is
     * resumed and takes priority over the default dashboard redirect.
     */
    public function store(
        LoginWithPasswordRequest $request,
        CompletePendingSharedClaim $completePendingSharedClaim,
        StorePendingSharedClaim $storePendingSharedClaim,
    ): RedirectResponse {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $storePendingSharedClaim->handle(
            $request,
            $request->string('pending_claim_roster_token')->value() ?: null,
            $request->integer('pending_claim_item_id') ?: null,
            $request->input('pending_claim_quantity') !== null ? (float) $request->input('pending_claim_quantity') : null,
        );

        if ($redirect = $completePendingSharedClaim->handle($request, $request->user())) {
            return $redirect;
        }

        return redirect()->route('dashboard');
    }

    /**
     * Log the user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }
}
