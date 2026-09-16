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
        Schema::table('polls', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('chosen_option_id')->nullable()->constrained('poll_options')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chosen_option_id');
            $table->dropColumn('closed_at');
        });
    }
};
