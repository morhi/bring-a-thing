<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestMagicLinkRequest;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MagicLinkController extends Controller
{
    /**
     * Issue a magic link to the given email address.
     *
     * Serves both returning and brand-new users: an unknown or nameless
     * email is created as a fresh account (registration and login are the
     * same request here). An optional roster name, from the landing page's
     * guided onboarding, creates a first standalone roster up front so it's
     * already waiting once the link is followed.
     */
    public function store(RequestMagicLinkRequest $request, FindOrCreateUserByEmail $findOrCreateUser, SendMagicLink $sendMagicLink): RedirectResponse
    {
        $user = $findOrCreateUser->handle($request->string('email')->value(), $request->string('name')->value() ?: null);

        $roster = null;
        $rosterName = $request->string('roster_name')->value();

        if ($rosterName !== '') {
            $roster = Roster::query()->make(['title' => $rosterName]);
            $roster->owner_id = $user->getKey();
            $roster->save();
        }

        $sendMagicLink->handle($user, $roster);

        return back()->with('success', 'A login link has been sent to your email address.');
    }

    /**
     * Report whether logging in with this email would require a name, and
     * whether it should prompt for a password instead of a magic link, so
     * the login form can reveal the right next step before the user submits.
     */
    public function needsName(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = $request->string('email')->value();

        return response()->json([
            'needsName' => User::emailNeedsName($email),
            'hasPassword' => User::emailHasPassword($email),
        ]);
    }

    /**
     * Consume a signed magic link and start the user's session.
     *
     * A `roster` query parameter, set when the link was issued right after
     * onboarding created a first roster, lands the user there instead of
     * the dashboard.
     */
    public function show(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);

        $roster = Roster::query()->find($request->integer('roster'));

        if ($roster && $roster->owner_id === $user->getKey()) {
            return redirect()->route('rosters.show', $roster);
        }

        return redirect()->route('dashboard');
    }
}
