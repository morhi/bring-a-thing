<?php

namespace App\Actions\Polls;

use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Support\Facades\DB;

class UpdatePoll
{
    /**
     * Save the poll edit page: rename, change option rows and add new ones (F-08).
     *
     * Rows with a slug update that option, rows without one are added.
     * Options missing from the rows stay unless their slug is in $remove,
     * which deletes them with their responses; the page confirmed that.
     * The request has already rejected removing fixed options and date or
     * time changes on locked options, so their label is the only change left.
     *
     * @param  array<int, array{slug?: string|null, date: string, starts_at?: string|null, ends_at?: string|null, label?: string|null}>|null  $options
     * @param  array<int, string>  $remove
     */
    public function handle(Poll $poll, string $title, ?array $options, array $remove = []): void
    {
        DB::transaction(function () use ($poll, $title, $options, $remove) {
            $poll->update(['title' => $title]);

            if ($remove !== []) {
                $poll->options()->whereIn('slug', $remove)->get()->each->delete();
            }

            if ($options === null) {
                return;
            }

            $existing = $poll->options()->get()->keyBy('slug');

            foreach ($options as $row) {
                $attributes = [
                    'date' => $row['date'],
                    'starts_at' => $row['starts_at'] ?? null,
                    'ends_at' => $row['ends_at'] ?? null,
                    'label' => $row['label'] ?? null,
                ];

                /** @var PollOption|null $option */
                $option = $existing->get($row['slug'] ?? '');

                if ($option === null) {
                    $poll->options()->create($attributes);

                    continue;
                }

                $option->update($option->hasLockedSchedule() ? ['label' => $attributes['label']] : $attributes);
            }
        });
    }
}
