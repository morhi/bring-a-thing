<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SendEmailConfirmationLink;
use App\Http\Requests\AddAccountEmailRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Store an email for a name-only account and send a confirmation link to it.
     *
     * The address stays pending until confirmed, so nobody can attach an
     * email they do not control and take over its later logins (F-01).
     * A new request replaces the previous pending address.
     */
    public function addEmail(AddAccountEmailRequest $request, SendEmailConfirmationLink $sendConfirmationLink): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['pending_email' => $request->string('email')->value()])->save();

        $sendConfirmationLink->handle($user);

        return back()->with('success', 'A confirmation link has been sent to your email address.');
    }

    /**
     * Confirm the pending email through its signed link and make it the login email.
     *
     * Only the logged-in owner of the account can confirm, and only the
     * latest pending address. Once confirmed, the link is spent because
     * the account has an email.
     */
    public function confirmEmail(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->is($user), 403);

        $pendingEmail = $user->pending_email;

        abort_if($user->email !== null || $pendingEmail === null, 403);
        abort_unless(hash_equals(SendEmailConfirmationLink::hashFor($pendingEmail), $request->string('hash')->value()), 403);

        if (User::query()->where('email', $pendingEmail)->exists()) {
            $user->forceFill(['pending_email' => null])->save();

            return to_route('settings.edit')->withErrors(['email' => __('ui.settings.email.taken')]);
        }

        $user->forceFill([
            'email' => $pendingEmail,
            'email_verified_at' => now(),
            'pending_email' => null,
        ])->save();

        return to_route('settings.edit')->with('success', 'Email confirmed. You can now log in with it on other devices.');
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
