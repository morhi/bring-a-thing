<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CalendarFeedSettingsController extends Controller
{
    /**
     * Turn on the calendar feed for the signed-in account.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->user()->enableCalendarFeed();

        return back()->with('success', __('ui.settings.calendar.enabled'));
    }

    /**
     * Replace the calendar link, invalidating the old one.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->user()->regenerateCalendarToken();

        return back()->with('success', __('ui.settings.calendar.regenerated'));
    }

    /**
     * Turn off the calendar feed.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->disableCalendarFeed();

        return back()->with('success', __('ui.settings.calendar.disabled'));
    }
}
