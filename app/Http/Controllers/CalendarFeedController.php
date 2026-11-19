<?php

namespace App\Http\Controllers;

use App\Actions\Calendar\BuildCalendarFeed;
use App\Models\User;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    /**
     * Serve the calendar feed for the account owning the token.
     *
     * An unknown, replaced or revoked token is a plain 404.
     */
    public function __invoke(string $token, BuildCalendarFeed $buildCalendarFeed): Response
    {
        $user = User::where('calendar_token', $token)->firstOrFail();

        return response($buildCalendarFeed->handle($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="bring-a-thing.ics"',
        ]);
    }
}
