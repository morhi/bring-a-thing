<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The zone the floating wall-clock times were entered in before this migration.
     */
    private const string ZONE = 'Europe/Zurich';

    /**
     * Run the migrations.
     *
     * Timed starts and ends become UTC instants and drop their date, so a
     * row holds either a plain date or an instant per side (section 9).
     */
    public function up(): void
    {
        foreach (['events' => 'end_date', 'poll_options' => null] as $table => $endDateColumn) {
            Schema::table($table, function (Blueprint $blueprint) use ($endDateColumn) {
                $blueprint->date('date')->nullable()->change();

                if ($endDateColumn !== null) {
                    $blueprint->date($endDateColumn)->nullable()->change();
                }

                $blueprint->dateTime('starts_at_utc')->nullable();
                $blueprint->dateTime('ends_at_utc')->nullable();
            });

            DB::table($table)->where(fn ($query) => $query->whereNotNull('starts_at')->orWhereNotNull('ends_at'))->orderBy('id')->chunkById(200, function ($rows) use ($table, $endDateColumn) {
                foreach ($rows as $row) {
                    $endDate = $endDateColumn === null ? $row->date : $row->{$endDateColumn};
                    $changes = [
                        'starts_at_utc' => $this->toUtc($row->date, $row->starts_at),
                        'ends_at_utc' => $this->toUtc($endDate, $row->ends_at),
                    ];

                    if ($row->starts_at !== null) {
                        $changes['date'] = null;
                    }

                    if ($endDateColumn !== null && $row->ends_at !== null) {
                        $changes[$endDateColumn] = null;
                    }

                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            });

            $this->replaceColumns($table, '_utc');
        }
    }

    /**
     * Reverse the migrations.
     *
     * Instants turn back into a Zurich date and wall-clock time.
     */
    public function down(): void
    {
        foreach (['events' => 'end_date', 'poll_options' => null] as $table => $endDateColumn) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->time('starts_at_local')->nullable();
                $blueprint->time('ends_at_local')->nullable();
            });

            DB::table($table)->where(fn ($query) => $query->whereNotNull('starts_at')->orWhereNotNull('ends_at'))->orderBy('id')->chunkById(200, function ($rows) use ($table, $endDateColumn) {
                foreach ($rows as $row) {
                    $start = $this->toLocal($row->starts_at);
                    $end = $this->toLocal($row->ends_at);
                    $changes = ['starts_at_local' => $start?->format('H:i:s'), 'ends_at_local' => $end?->format('H:i:s')];

                    if ($start !== null) {
                        $changes['date'] = $start->format('Y-m-d');
                    }

                    if ($endDateColumn !== null && $end !== null) {
                        $changes[$endDateColumn] = $end->format('Y-m-d');
                    }

                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            });

            $this->replaceColumns($table, '_local');

            Schema::table($table, function (Blueprint $blueprint) use ($endDateColumn) {
                $blueprint->date('date')->nullable(false)->change();

                if ($endDateColumn !== null) {
                    $blueprint->date($endDateColumn)->nullable(false)->change();
                }
            });
        }
    }

    /**
     * Swap the old start and end columns for the converted ones.
     */
    private function replaceColumns(string $table, string $suffix): void
    {
        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn(['starts_at', 'ends_at']);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($suffix) {
            $blueprint->renameColumn('starts_at'.$suffix, 'starts_at');
            $blueprint->renameColumn('ends_at'.$suffix, 'ends_at');
        });
    }

    /**
     * The UTC instant of a Zurich date and wall-clock time, or null without a time.
     */
    private function toUtc(?string $date, ?string $time): ?string
    {
        if ($date === null || $time === null) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', substr($date, 0, 10).' '.substr($time.':00', 0, 8), self::ZONE)
            ->utc()
            ->format('Y-m-d H:i:s');
    }

    /**
     * The Zurich wall-clock moment of a stored UTC instant, or null without one.
     */
    private function toLocal(?string $instant): ?Carbon
    {
        if ($instant === null) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', substr($instant, 0, 19), 'UTC')->setTimezone(self::ZONE);
    }
};
