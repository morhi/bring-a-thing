<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SendEmailConfirmationLink;
use App\Data\SettingsData;
use App\Http\Requests\AddAccountEmailRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Events\NullDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Show the account settings page (F-02, F-24).
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Settings', [
            'settings' => SettingsData::fromUser($request->user()),
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

        return back()->with('success', __('ui.settings.saved'));
    }

    /**
     * Store an email for a name-only account and send a confirmation link to it.
     *
     * The address stays pending until confirmed, so nobody can attach an
     * email they do not control and take over its later logins (F-01).
     * A new request replaces the previous pending address. A registered
     * address gets the same answer and pending state but no mail, so the
     * response does not reveal which emails exist.
     */
    public function addEmail(AddAccountEmailRequest $request, SendEmailConfirmationLink $sendConfirmationLink): RedirectResponse
    {
        $user = $request->user();
        $email = $request->string('email')->value();
        $user->forceFill(['pending_email' => $email])->save();

        if (! User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->exists()) {
            $sendConfirmationLink->handle($user);
        }

        return back()->with('success', __('ui.settings.email.sent'));
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

        return to_route('settings.edit')->with('success', __('ui.settings.email.confirmed'));
    }

    /**
     * Set or change the user's password.
     *
     * The remember cookie embeds the password hash, so it is re-issued to keep
     * the current device logged in (F-02).
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $hadPassword = $request->user()->password !== null;

        $request->user()->update([
            'password' => $request->string('password')->value(),
        ]);

        $this->reissueRememberCookie($request->user());

        return back()->with('success', __($hadPassword ? 'ui.settings.password.changeDone' : 'ui.settings.password.setDone'));
    }

    /**
     * Re-issue the remember cookie for the current device without a Login event.
     *
     * Listeners of Login would start the passkey re-authentication window and
     * accept pending group invitations, neither of which a password change proves.
     */
    private function reissueRememberCookie(User $user): void
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard();
        $events = $guard->getDispatcher();

        $guard->setDispatcher(new NullDispatcher($events));

        try {
            $guard->login($user, true);
        } finally {
            $guard->setDispatcher($events);
        }
    }
}
