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
        Schema::create('roster_item_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['roster_item_id', 'custom_field_id'], 'roster_item_custom_field_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_item_custom_field_values');
    }
};
