<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;
use Spatie\IcalendarGenerator\Components\Calendar;

class CalendarFeedController extends Controller
{
    /**
     * Serve the calendar feed for the account owning the token.
     *
     * An unknown, replaced or revoked token is a plain 404.
     */
    public function __invoke(string $token): Response
    {
        User::where('calendar_token', $token)->firstOrFail();

        return response(Calendar::create(config('app.name'))->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="bring-a-thing.ics"',
        ]);
    }
}
