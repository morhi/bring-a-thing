<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The tables that gain a slug column, used as their route key instead of the incrementing id.
     *
     * @var list<string>
     */
    private array $tables = ['groups', 'rosters', 'roster_items', 'custom_fields'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('slug')->nullable()->unique()->after('id');
            });

            DB::table($table)->whereNull('slug')->pluck('id')->each(
                fn (int $id) => DB::table($table)->where('id', $id)->update(['slug' => Str::random(32)]),
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }
    }
};
