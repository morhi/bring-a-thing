<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A poll option counts as fixed when an event originates from it (F-09),
     * so the single chosen option is turned into such an event before the
     * column goes. Participation follows the F-09 derivation: yes becomes
     * "accepted", maybe becomes "pending". Plain query builder keeps the
     * migration independent of later model changes.
     */
    public function up(): void
    {
        $polls = DB::table('polls')
            ->join('poll_options', 'poll_options.id', '=', 'polls.chosen_option_id')
            ->whereNotNull('polls.chosen_option_id')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('events')->whereColumn('events.origin_poll_option_id', 'polls.chosen_option_id'))
            ->get(['polls.id', 'polls.title', 'polls.group_id', 'polls.organizer_id', 'polls.event_id', 'poll_options.id as option_id', 'poll_options.date', 'poll_options.starts_at', 'poll_options.ends_at']);

        foreach ($polls as $poll) {
            $now = now();
            $eventId = DB::table('events')->insertGetId([
                'slug' => Str::random(32),
                'group_id' => $poll->group_id,
                'organizer_id' => $poll->organizer_id,
                'title' => $poll->title,
                'date' => substr((string) $poll->date, 0, 10),
                'starts_at' => $poll->starts_at,
                'end_date' => $poll->ends_at === null ? null : substr((string) $poll->date, 0, 10),
                'ends_at' => $poll->ends_at,
                'origin_poll_option_id' => $poll->option_id,
                'parent_event_id' => $poll->event_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $participations = DB::table('poll_responses')
                ->where('poll_option_id', $poll->option_id)
                ->whereIn('status', ['yes', 'maybe'])
                ->get(['user_id', 'status'])
                ->map(fn ($response) => [
                    'event_id' => $eventId,
                    'user_id' => $response->user_id,
                    'status' => $response->status === 'yes' ? 'accepted' : 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('event_participations')->insert($participations->all());
        }

        Schema::table('polls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chosen_option_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restores the column with the earliest fixed option per poll; the
     * events created on the way up stay, since people may have used them.
     */
    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->foreignId('chosen_option_id')->nullable()->constrained('poll_options')->nullOnDelete();
        });

        DB::table('polls')->update([
            'chosen_option_id' => DB::table('poll_options')
                ->join('events', 'events.origin_poll_option_id', '=', 'poll_options.id')
                ->whereColumn('poll_options.poll_id', 'polls.id')
                ->select(DB::raw('min(poll_options.id)')),
        ]);
    }
};
