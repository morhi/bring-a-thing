<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Polls and events without a group are standalone, like lists (F-29).
     */
    public function up(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Standalone polls and events have no group to fall back to, so they go.
     */
    public function down(): void
    {
        DB::table('polls')->whereNull('group_id')->delete();
        DB::table('events')->whereNull('group_id')->delete();

        Schema::table('polls', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable(false)->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable(false)->change();
        });
    }
};
