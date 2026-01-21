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
        Schema::table('announcements', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('occurs_on_date');
            $table->boolean('always_show')->default(false)->after('day_of_week');
            $table->time('occurs_at_time')->nullable()->after('always_show');
            $table->string('location')->nullable()->after('occurs_at_time');
            $table->string('attachment_path')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn([
                'end_date',
                'always_show',
                'occurs_at_time',
                'location',
                'attachment_path',
            ]);
        });
    }
};
