<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestMagicLinkRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MagicLinkController extends Controller
{
    /**
     * Issue a magic link to the given email address.
     */
    public function store(RequestMagicLinkRequest $request, FindOrCreateUserByEmail $findOrCreateUser, SendMagicLink $sendMagicLink): RedirectResponse
    {
        $user = $findOrCreateUser->handle($request->string('email')->value(), $request->string('name')->value() ?: null);

        $sendMagicLink->handle($user);

        return back()->with('success', 'A login link has been sent to your email address.');
    }

    /**
     * Report whether logging in with this email would require a name, so the
     * login form can reveal the name field before the user submits.
     */
    public function needsName(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        return response()->json([
            'needsName' => User::emailNeedsName($request->string('email')->value()),
        ]);
    }

    /**
     * Consume a signed magic link and start the user's session.
     */
    public function show(User $user): RedirectResponse
    {
        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
