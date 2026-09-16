<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\RosterItemClaimController;
use App\Http\Controllers\RosterItemController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [MagicLinkController::class, 'store'])->name('magic-link.store');
    Route::post('/login/password', [AuthenticatedSessionController::class, 'store'])->name('login.password');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/login/needs-name', [MagicLinkController::class, 'needsName'])->name('magic-link.needs-name');
});

Route::get('/login/{user}', [MagicLinkController::class, 'show'])
    ->middleware('signed')
    ->name('login.consume');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
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

    Route::post('/rosters/{roster}/items', [RosterItemController::class, 'store'])->name('rosters.items.store');
    Route::patch('/rosters/{roster}/items/{item}', [RosterItemController::class, 'update'])->name('rosters.items.update');
    Route::delete('/rosters/{roster}/items/{item}', [RosterItemController::class, 'destroy'])->name('rosters.items.destroy');
    Route::post('/rosters/{roster}/items/{item}/claim', [RosterItemClaimController::class, 'store'])->name('rosters.items.claim.store');
    Route::delete('/rosters/{roster}/items/{item}/claim', [RosterItemClaimController::class, 'destroy'])->name('rosters.items.claim.destroy');

    Route::post('/rosters/{roster}/custom-fields', [CustomFieldController::class, 'store'])->name('rosters.custom-fields.store');
    Route::patch('/rosters/{roster}/custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('rosters.custom-fields.update');
    Route::delete('/rosters/{roster}/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('rosters.custom-fields.destroy');
});
