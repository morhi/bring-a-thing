<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestMagicLinkRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class MagicLinkController extends Controller
{
    /**
     * Issue a magic link to the given email address.
     */
    public function store(RequestMagicLinkRequest $request, FindOrCreateUserByEmail $findOrCreateUser, SendMagicLink $sendMagicLink): RedirectResponse
    {
        $user = $findOrCreateUser->handle($request->string('email')->value());

        $sendMagicLink->handle($user);

        return back()->with('success', 'A login link has been sent to your email address.');
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
