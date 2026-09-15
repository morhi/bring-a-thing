<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Register a user by email and send them a magic link to finish logging in.
     */
    public function store(RegisterRequest $request, FindOrCreateUserByEmail $findOrCreateUser, SendMagicLink $sendMagicLink): RedirectResponse
    {
        $user = $findOrCreateUser->handle($request->string('email')->value());

        $sendMagicLink->handle($user);

        return back()->with('success', 'A login link has been sent to your email address.');
    }
}
