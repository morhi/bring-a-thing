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
        Schema::create('roster_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit')->nullable();
            $table->text('notes')->nullable();
            $table->date('date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_items');
    }
};
