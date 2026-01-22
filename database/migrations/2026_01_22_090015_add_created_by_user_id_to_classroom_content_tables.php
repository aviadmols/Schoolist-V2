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
        Schema::table('important_contacts', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('classroom_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('children', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('classroom_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('child_contacts', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('child_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('class_links', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('classroom_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('classroom_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('classroom_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });

        Schema::table('class_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });

        Schema::table('child_contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });

        Schema::table('children', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });

        Schema::table('important_contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
