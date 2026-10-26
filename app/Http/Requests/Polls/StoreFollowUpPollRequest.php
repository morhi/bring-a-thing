<?php

namespace App\Http\Requests\Polls;

use Illuminate\Support\Facades\Gate;

class StoreFollowUpPollRequest extends StorePollRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Validation follows the group poll (F-06, F-07); only who may create
     * it differs (F-12). The gate throws itself, so people outside the
     * group get "Not found" (section 8) rather than "Forbidden".
     */
    public function authorize(): bool
    {
        Gate::forUser($this->user())->authorize('createFollowUpPoll', $this->route('event'));

        return true;
    }
}
