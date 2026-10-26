<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SendMagicLink;
use App\Http\Requests\AddAccountEmailRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
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
            'name' => request()->user()->name,
            'email' => request()->user()->email,
            'hasPassword' => (bool) request()->user()->password,
        ]);
    }

    /**
     * Set or change the user's display name.
     */
    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update([
            'name' => $request->string('name')->value(),
        ]);

        return back()->with('success', 'Profile updated.');
    }

    /**
     * Add an email to a name-only account and send a login link to it.
     *
     * The login link keeps access on other devices (F-01).
     */
    public function addEmail(AddAccountEmailRequest $request, SendMagicLink $sendMagicLink): RedirectResponse
    {
        $user = $request->user();
        $user->update(['email' => $request->string('email')->value()]);

        $sendMagicLink->handle($user);

        return back()->with('success', 'Email added. A login link has been sent to it.');
    }

    /**
     * Set or change the user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->string('password')->value(),
        ]);

        return back()->with('success', 'Password updated.');
    }
}
