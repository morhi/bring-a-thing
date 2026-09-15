<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Show the account settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('Settings', [
            'hasPassword' => (bool) request()->user()->password,
        ]);
    }

    /**
     * Set or change the user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->string('password')->value(),
        ]);

        return back()->with('status', 'Password updated.');
    }
}
