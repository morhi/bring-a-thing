<?php

namespace App\Actions\Auth;

use App\Actions\Access\GrantEntityAccess;
use App\Actions\Groups\JoinGroupViaLink;
use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompletePendingJoin
{
    public function __construct(
        private readonly JoinGroupViaLink $joinGroup,
        private readonly GrantEntityAccess $grantAccess,
    ) {}

    /**
     * Resolve the invite link a login was started from (F-04, F-26, F-27).
     *
     * The join page's login form sends the token as `join`, `join_event` or
     * `join_poll`, the same names the magic link carries in its URL. An
     * unknown, revoked or replaced token resolves to nothing, so the login
     * carries on as if it had started anywhere else.
     */
    public function resolve(Request $request): Group|Event|Poll|null
    {
        $groupToken = $this->token($request, 'join');

        if ($groupToken !== null) {
            return Group::query()->where('invite_token', $groupToken)->first();
        }

        $eventToken = $this->token($request, 'join_event');

        if ($eventToken !== null) {
            return Event::query()->where('invite_token', $eventToken)->first();
        }

        $pollToken = $this->token($request, 'join_poll');

        if ($pollToken !== null) {
            return Poll::query()->where('invite_token', $pollToken)->first();
        }

        return null;
    }

    /**
     * Finish the join a login was started for and land on what was joined.
     *
     * A group needs a verified email (F-04): without one the account returns
     * to the join page, which asks for the missing step. An event or poll
     * link grants access right away (F-26, F-27). Returns null when the
     * login carries no valid invite token.
     */
    public function handle(Request $request, User $user): ?RedirectResponse
    {
        $target = $this->resolve($request);

        if ($target === null) {
            return null;
        }

        if ($target instanceof Group) {
            return $this->joinGroup($target, $user);
        }

        $redirect = redirect()->route($target instanceof Event ? 'events.show' : 'polls.show', $target);

        if (! $this->grantAccess->handle($target, $user)) {
            return $redirect;
        }

        return $redirect->with('success', __('ui.entityJoin.joined'));
    }

    /**
     * Join the group if the account has a verified email, else return to the join page.
     */
    private function joinGroup(Group $group, User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('groups.join.show', $group->invite_token);
        }

        $redirect = redirect()->route('groups.show', $group);

        if (! $this->joinGroup->handle($group, $user)) {
            return $redirect;
        }

        return $redirect->with('success', __('ui.join.joined'));
    }

    /**
     * Read one invite token field, ignoring empty or non-string input.
     */
    private function token(Request $request, string $field): ?string
    {
        $value = $request->input($field);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
