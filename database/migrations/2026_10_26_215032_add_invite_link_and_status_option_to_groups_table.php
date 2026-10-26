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
        Schema::table('groups', function (Blueprint $table) {
            $table->string('invite_token')->nullable()->unique()->after('owner_id');
            $table->boolean('invite_link_locked')->default(false)->after('invite_token');
            $table->boolean('members_can_set_status')->default(false)->after('invite_link_locked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique(['invite_token']);
            $table->dropColumn(['invite_token', 'invite_link_locked', 'members_can_set_status']);
        });
    }
};
