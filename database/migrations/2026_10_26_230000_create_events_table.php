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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('date');
            $table->time('starts_at')->nullable();
            $table->date('end_date')->nullable();
            $table->time('ends_at')->nullable();
            $table->foreignId('origin_poll_option_id')->nullable()->constrained('poll_options')->nullOnDelete();
            $table->foreignId('parent_event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
