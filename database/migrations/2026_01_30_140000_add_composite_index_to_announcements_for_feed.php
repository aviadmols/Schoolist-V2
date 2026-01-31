<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite index to speed up announcement feed queries that filter by
     * classroom_id and occurs_on_date (e.g. AnnouncementFeedService::getActiveFeed).
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->index(['classroom_id', 'occurs_on_date'], 'announcements_classroom_occurs_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('announcements_classroom_occurs_index');
        });
    }
};
