<?php

namespace App\Http\Controllers;

use App\Actions\Access\GrantEntityAccess;
use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Data\EntityJoinData;
use App\Http\Requests\Access\JoinEntityRequest;
use App\Models\Event;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EntityJoinController extends Controller
{
    /**
     * Show the join page of an event's invite link (F-26).
     */
    public function showEvent(Request $request, string $token): SymfonyResponse
    {
        $event = Event::query()->with('organizer')->where('invite_token', $token)->first();

        if ($event === null) {
            return $this->notFound($request, 'Events/Join');
        }

        $user = $request->user();

        if ($user !== null && $event->admits($user)) {
            return to_route('events.show', $event);
        }

        return Inertia::render('Events/Join', [
            'join' => EntityJoinData::fromEvent($event, $user),
        ])->toResponse($request);
    }

    /**
     * Show the join page of a poll's invite link (F-27).
     */
    public function showPoll(Request $request, string $token): SymfonyResponse
    {
        $poll = Poll::query()->with('organizer')->where('invite_token', $token)->first();

        if ($poll === null) {
            return $this->notFound($request, 'Polls/Join');
        }

        $user = $request->user();

        if ($user !== null && $poll->isOpenTo($user)) {
            return to_route('polls.show', $poll);
        }

        return Inertia::render('Polls/Join', [
            'join' => EntityJoinData::fromPoll($poll, $user),
        ])->toResponse($request);
    }

    /**
     * Join the event through its invite link (F-26).
     */
    public function storeEvent(JoinEntityRequest $request, string $token, GrantEntityAccess $grantAccess, FindOrCreateUserByEmail $findOrCreateUser, SendMagicLink $sendMagicLink): RedirectResponse
    {
        return $this->join($request, $grantAccess, $findOrCreateUser, $sendMagicLink, 'events.show');
    }

    /**
     * Join the poll through its invite link (F-27).
     */
    public function storePoll(JoinEntityRequest $request, string $token, GrantEntityAccess $grantAccess, FindOrCreateUserByEmail $findOrCreateUser, SendMagicLink $sendMagicLink): RedirectResponse
    {
        return $this->join($request, $grantAccess, $findOrCreateUser, $sendMagicLink, 'polls.show');
    }

    /**
     * Grant access through the invite link and land on the entity.
     *
     * Logged-in accounts join at once. A guest with an email receives a
     * login link that grants access on use. A guest with a name only gets
     * an account without email that stays logged in on this browser; it
     * loses access at logout, which the join page warns about.
     */
    private function join(
        JoinEntityRequest $request,
        GrantEntityAccess $grantAccess,
        FindOrCreateUserByEmail $findOrCreateUser,
        SendMagicLink $sendMagicLink,
        string $showRoute,
    ): RedirectResponse {
        $entity = $request->entity();
        $user = $request->user();

        if ($user !== null) {
            $grantAccess->handle($entity, $user);

            return to_route($showRoute, $entity)->with('success', __('ui.entityJoin.joined'));
        }

        if ($request->filled('email')) {
            $user = $findOrCreateUser->handle($request->string('email')->value(), $request->string('name')->value() ?: null);
            $sendMagicLink->handle($user, grantAccessTo: $entity);

            return back()->with('success', __('ui.welcome.login.sentFlash'));
        }

        $user = User::query()->create([
            'name' => $request->string('name')->value(),
            'email' => null,
            'language' => app()->getLocale(),
            'password' => null,
        ]);
        $grantAccess->handle($entity, $user);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return to_route($showRoute, $entity)->with('success', __('ui.entityJoin.joined'));
    }

    /**
     * Render the join page's "Not found" state for an unknown, revoked or replaced token.
     */
    private function notFound(Request $request, string $component): SymfonyResponse
    {
        return Inertia::render($component, ['join' => null])->toResponse($request)->setStatusCode(404);
    }
}
