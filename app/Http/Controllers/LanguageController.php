<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Http\Requests\UpdateLanguageRequest;
use Illuminate\Http\RedirectResponse;

class LanguageController extends Controller
{
    /**
     * Switch the interface language.
     *
     * Always remembered in the session so guests keep it; a logged-in
     * account also stores it so the choice follows them across devices.
     */
    public function update(UpdateLanguageRequest $request): RedirectResponse
    {
        $locale = Locale::from($request->string('language')->value());

        $request->session()->put('locale', $locale->value);
        $request->user()?->update(['language' => $locale->value]);

        return back()->with('success', 'Language updated.');
    }
}
