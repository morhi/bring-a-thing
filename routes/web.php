<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\RosterItemClaimController;
use App\Http\Controllers\RosterItemController;
use App\Http\Controllers\RosterSharingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SharedRosterClaimController;
use App\Http\Controllers\SharedRosterController;
use App\Http\Controllers\SharedRosterItemController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('welcome');

// No standalone login page: the welcome page's hero form is the single
// entry point. This name only exists because Laravel's auth middleware
// bounces guests to route('login') by convention; GET-only so it doesn't
// shadow the POST /login endpoints below.
Route::get('/login', fn () => redirect()->route('welcome'))->name('login');

Route::middleware('guest')->group(function () {
    Route::post('/login', [MagicLinkController::class, 'store'])->name('magic-link.store');
    Route::post('/login/password', [AuthenticatedSessionController::class, 'store'])->name('login.password');
    Route::get('/login/needs-name', [MagicLinkController::class, 'needsName'])->name('magic-link.needs-name');
});

Route::get('/login/{user}', [MagicLinkController::class, 'show'])
    ->middleware('signed')
    ->name('login.consume');

// Shared list links: viewable and claimable by anyone with the link, logged
// in or not. No auth/guest middleware here on purpose.
Route::get('/shared/{token}', [SharedRosterController::class, 'show'])->name('shared-rosters.show');
Route::post('/shared/{token}/items/{item}/claim', [SharedRosterClaimController::class, 'store'])->name('shared-rosters.items.claim.store');
Route::post('/shared/{token}/items', [SharedRosterItemController::class, 'store'])->name('shared-rosters.items.store');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::patch('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('/groups/{group}/edit', [GroupController::class, 'edit'])->name('groups.edit');
    Route::patch('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('/groups/{group}/invite', [GroupController::class, 'invite'])->name('groups.invite');
    Route::delete('/groups/{group}/members/{member}', [GroupController::class, 'removeMember'])->name('groups.members.destroy');

    Route::post('/rosters', [RosterController::class, 'store'])->name('rosters.store');
    Route::get('/rosters/{roster}', [RosterController::class, 'show'])->name('rosters.show');
    Route::get('/rosters/{roster}/edit', [RosterController::class, 'edit'])->name('rosters.edit');
    Route::patch('/rosters/{roster}', [RosterController::class, 'update'])->name('rosters.update');
    Route::delete('/rosters/{roster}', [RosterController::class, 'destroy'])->name('rosters.destroy');
    Route::post('/rosters/{roster}/duplicate', [RosterController::class, 'duplicate'])->name('rosters.duplicate');

    Route::post('/rosters/{roster}/sharing', [RosterSharingController::class, 'store'])->name('rosters.sharing.store');
    Route::post('/rosters/{roster}/sharing/regenerate', [RosterSharingController::class, 'regenerate'])->name('rosters.sharing.regenerate');
    Route::delete('/rosters/{roster}/sharing', [RosterSharingController::class, 'destroy'])->name('rosters.sharing.destroy');

    Route::post('/rosters/{roster}/items', [RosterItemController::class, 'store'])->name('rosters.items.store');
    Route::patch('/rosters/{roster}/items/{item}', [RosterItemController::class, 'update'])->name('rosters.items.update');
    Route::delete('/rosters/{roster}/items/{item}', [RosterItemController::class, 'destroy'])->name('rosters.items.destroy');
    Route::post('/rosters/{roster}/items/{item}/claim', [RosterItemClaimController::class, 'store'])->name('rosters.items.claim.store');
    Route::delete('/rosters/{roster}/items/{item}/claim', [RosterItemClaimController::class, 'destroy'])->name('rosters.items.claim.destroy');

    Route::post('/rosters/{roster}/custom-fields', [CustomFieldController::class, 'store'])->name('rosters.custom-fields.store');
    Route::patch('/rosters/{roster}/custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('rosters.custom-fields.update');
    Route::delete('/rosters/{roster}/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('rosters.custom-fields.destroy');
});
