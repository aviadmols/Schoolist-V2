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
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->integer('day_of_week'); // 0 (Sun) to 6 (Sat)
            $table->time('start_time');
            $table->time('end_time');
            $table->string('subject');
            $table->string('teacher')->nullable();
            $table->string('room')->nullable();
            $table->timestamps();

            $table->index(['classroom_id', 'day_of_week']);
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('timetable_file_id')->nullable()->constrained('files')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('timetable_file_id');
        });
        Schema::dropIfExists('timetable_entries');
    }
};
