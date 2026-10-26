<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rosters', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('group_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('polls', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('group_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::table('rosters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
