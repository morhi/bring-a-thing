<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompletePendingJoin;
use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Actions\Auth\StartLoginWindow;
use App\Actions\Sharing\CompletePendingSharedClaim;
use App\Actions\Sharing\StorePendingSharedClaim;
use App\Enums\LoginLinkError;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestMagicLinkRequest;
use App\Models\Group;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class MagicLinkController extends Controller
{
    /**
     * Issue a magic link to the given email address.
     *
     * Serves both returning and brand-new users: an unknown or nameless
     * email is created as a fresh account (registration and login are the
     * same request here). An optional roster name, from the landing page's
     * guided onboarding, creates a first standalone roster up front so it's
     * already waiting once the link is followed. Pending-claim fields, set
     * when this form is opened from a shared list's login dialog, are
     * stashed so the claim completes automatically once the link is used.
     * An invite token from a join page's login form (F-04, F-26, F-27) goes
     * into the link, so following it joins; a revoked token is dropped.
     */
    public function store(
        RequestMagicLinkRequest $request,
        FindOrCreateUserByEmail $findOrCreateUser,
        SendMagicLink $sendMagicLink,
        StorePendingSharedClaim $storePendingSharedClaim,
        CompletePendingJoin $completePendingJoin,
    ): RedirectResponse {
        $user = $findOrCreateUser->handle($request->string('email')->value(), $request->string('name')->value() ?: null);

        $roster = null;
        $rosterName = $request->string('roster_name')->value();

        if ($rosterName !== '') {
            $roster = Roster::query()->make(['title' => $rosterName]);
            $roster->owner_id = $user->getKey();
            $roster->save();
        }

        $storePendingSharedClaim->handle(
            $request,
            $request->string('pending_claim_roster_token')->value() ?: null,
            $request->integer('pending_claim_item_id') ?: null,
            $request->input('pending_claim_quantity') !== null ? (float) $request->input('pending_claim_quantity') : null,
        );

        $joinTarget = $completePendingJoin->resolve($request);

        $sendMagicLink->handle(
            $user,
            $roster,
            $joinTarget instanceof Group ? $joinTarget : null,
            $joinTarget instanceof Group ? null : $joinTarget,
        );

        return back()->with('success', __('ui.welcome.login.sentFlash'));
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
     * the dashboard. A pending claim from a shared list link, started
     * before login, takes priority over both. A `join` invite token, set
     * when the link was requested through a closed invite link, joins that
     * group and lands there, unless the invite link was revoked meanwhile.
     * A `join_event` or `join_poll` token does the same for an event or
     * poll invite link (F-26, F-27): it grants access and lands there.
     *
     * An expired or altered link logs nobody in and returns to the start
     * page, which offers a new link (F-01, section 8).
     */
    public function show(
        Request $request,
        User $user,
        CompletePendingSharedClaim $completePendingSharedClaim,
        StartLoginWindow $startLoginWindow,
        CompletePendingJoin $completePendingJoin,
    ): RedirectResponse {
        if (! URL::hasValidSignature($request)) {
            $error = URL::hasCorrectSignature($request) ? LoginLinkError::Expired : LoginLinkError::Invalid;

            return to_route('welcome')->with('login_link_error', $error->value);
        }

        // Following the link proves control of the address (F-01).
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);
        $startLoginWindow->handle();

        if ($redirect = $completePendingSharedClaim->handle($request, $user)) {
            return $redirect;
        }

        if ($redirect = $completePendingJoin->handle($request, $user)) {
            return $redirect;
        }

        $roster = Roster::query()->find($request->integer('roster'));

        if ($roster && $roster->owner_id === $user->getKey()) {
            return redirect()->route('rosters.show', $roster);
        }

        return redirect()->route('dashboard');
    }
}
