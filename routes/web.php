<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevComponentsController;
use App\Http\Controllers\EventCommentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventFollowUpPollController;
use App\Http\Controllers\EventParticipationController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupInviteLinkController;
use App\Http\Controllers\GroupJoinController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\PollOptionController;
use App\Http\Controllers\PollResponseController;
use App\Http\Controllers\RosterCommentController;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\RosterItemClaimController;
use App\Http\Controllers\RosterItemCommentController;
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

// Language switch is open to guests (start page); logged-in accounts persist it.
Route::patch('/language', [LanguageController::class, 'update'])->name('language.update');

Route::middleware('guest')->group(function () {
    Route::post('/login', [MagicLinkController::class, 'store'])->name('magic-link.store');
    Route::post('/login/password', [AuthenticatedSessionController::class, 'store'])->name('login.password');
    Route::get('/login/needs-name', [MagicLinkController::class, 'needsName'])->name('magic-link.needs-name');
});

// The signature is checked in the controller, so a bad link lands on the start page with a note.
Route::get('/login/{user}', [MagicLinkController::class, 'show'])
    ->name('login.consume');

// Shared list links: viewable and claimable by anyone with the link, logged
// in or not. No auth/guest middleware here on purpose.
Route::get('/shared/{token}', [SharedRosterController::class, 'show'])->name('shared-rosters.show');
Route::post('/shared/{token}/items/{item}/claim', [SharedRosterClaimController::class, 'store'])->name('shared-rosters.items.claim.store');
Route::delete('/shared/{token}/items/{item}/claim', [SharedRosterClaimController::class, 'destroy'])->name('shared-rosters.items.claim.destroy');
Route::post('/shared/{token}/items', [SharedRosterItemController::class, 'store'])->name('shared-rosters.items.store');

// Group invite links: open to guests on purpose, joining is the point (F-04).
// A revoked or replaced token is "Not found"; the controller resolves it so
// the join page can render that state inside the app's layout.
Route::get('/join/{token}', [GroupJoinController::class, 'show'])->name('groups.join.show');
Route::post('/join/{token}', [GroupJoinController::class, 'store'])->name('groups.join.store');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::patch('/settings/email', [SettingsController::class, 'addEmail'])->name('settings.email');
    Route::get('/settings/email/confirm/{user}', [SettingsController::class, 'confirmEmail'])->middleware('signed')->name('settings.email.confirm');
    Route::patch('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/settings/friends', [FriendController::class, 'index'])->name('friends.index');
    Route::post('/settings/friends', [FriendController::class, 'store'])->name('friends.store');
    Route::delete('/settings/friends/{friend}', [FriendController::class, 'destroy'])->name('friends.destroy');

    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/new', [GroupController::class, 'create'])->name('groups.create');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('/groups/{group}/edit', [GroupController::class, 'edit'])->name('groups.edit');
    Route::patch('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('/groups/{group}/invite', [GroupController::class, 'invite'])->name('groups.invite');
    Route::post('/groups/{group}/invite-link', [GroupInviteLinkController::class, 'store'])->name('groups.invite-link.store');
    Route::patch('/groups/{group}/invite-link', [GroupInviteLinkController::class, 'update'])->name('groups.invite-link.update');
    Route::post('/groups/{group}/invite-link/regenerate', [GroupInviteLinkController::class, 'regenerate'])->name('groups.invite-link.regenerate');
    Route::delete('/groups/{group}/invite-link', [GroupInviteLinkController::class, 'destroy'])->name('groups.invite-link.destroy');
    Route::patch('/groups/{group}/members/{member}', [GroupController::class, 'updateMemberRole'])->name('groups.members.update');
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

    Route::post('/rosters/{roster}/comments', [RosterCommentController::class, 'store'])->name('rosters.comments.store');
    Route::delete('/rosters/{roster}/comments/{comment}', [RosterCommentController::class, 'destroy'])->name('rosters.comments.destroy');
    Route::post('/rosters/{roster}/items/{item}/comments', [RosterItemCommentController::class, 'store'])->name('rosters.items.comments.store');
    Route::delete('/rosters/{roster}/items/{item}/comments/{comment}', [RosterItemCommentController::class, 'destroy'])->name('rosters.items.comments.destroy');

    Route::post('/rosters/{roster}/custom-fields', [CustomFieldController::class, 'store'])->name('rosters.custom-fields.store');
    Route::patch('/rosters/{roster}/custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('rosters.custom-fields.update');
    Route::delete('/rosters/{roster}/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('rosters.custom-fields.destroy');

    Route::post('/groups/{group}/polls', [PollController::class, 'store'])->name('polls.store');
    Route::get('/polls/{poll}', [PollController::class, 'show'])->name('polls.show');
    Route::get('/polls/{poll}/edit', [PollController::class, 'edit'])->name('polls.edit');
    Route::patch('/polls/{poll}', [PollController::class, 'update'])->name('polls.update');
    Route::delete('/polls/{poll}', [PollController::class, 'destroy'])->name('polls.destroy');
    Route::post('/polls/{poll}/close', [PollController::class, 'close'])->name('polls.close');
    Route::delete('/polls/{poll}/close', [PollController::class, 'reopen'])->name('polls.reopen');

    Route::post('/polls/{poll}/options', [PollOptionController::class, 'store'])->name('polls.options.store');
    Route::delete('/polls/{poll}/options/{option}', [PollOptionController::class, 'destroy'])->name('polls.options.destroy');

    Route::post('/polls/{poll}/options/{option}/responses', [PollResponseController::class, 'store'])->name('polls.responses.store');
});

// Component catalogue for developers; never registered outside local.
if (app()->environment('local')) {
    Route::get('/dev/components', DevComponentsController::class)->name('dev.components');
}

// Events and participation (F-10, F-11).
Route::middleware('auth')->group(function () {
    Route::get('/groups/{group}/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/groups/{group}/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::patch('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::put('/events/{event}/participation', [EventParticipationController::class, 'update'])->name('events.participation.update');
    Route::put('/events/{event}/participants/{member}', [EventParticipationController::class, 'updateFor'])->name('events.participants.update');
    Route::delete('/events/{event}/participants/{member}', [EventParticipationController::class, 'destroy'])->name('events.participants.destroy');
});

// Poll create page and fixing dates (F-06, F-09).
Route::middleware('auth')->group(function () {
    Route::get('/groups/{group}/polls/create', [PollController::class, 'create'])->name('polls.create');
    Route::post('/polls/{poll}/fix', [PollController::class, 'fix'])->name('polls.fix');
});

// Lists: the "New list" page (F-13). Lives outside /rosters/ because
// /rosters/{roster} would capture a "create" segment.
Route::middleware('auth')->group(function () {
    Route::get('/lists/new', [RosterController::class, 'create'])->name('rosters.create');
});

// Follow-up polls within an event (F-12).
Route::middleware('auth')->group(function () {
    Route::get('/events/{event}/polls/create', [EventFollowUpPollController::class, 'create'])->name('events.polls.create');
    Route::post('/events/{event}/polls', [EventFollowUpPollController::class, 'store'])->name('events.polls.store');
});

// Event comment thread (F-20).
Route::middleware('auth')->group(function () {
    Route::post('/events/{event}/comments', [EventCommentController::class, 'store'])->name('events.comments.store');
    Route::delete('/events/{event}/comments/{comment}', [EventCommentController::class, 'destroy'])->name('events.comments.destroy');
});

// Bell notifications (F-23): JSON for the header panel, scoped to the signed-in account.
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});
