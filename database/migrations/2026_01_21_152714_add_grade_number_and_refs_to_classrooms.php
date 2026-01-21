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
        Schema::table('classrooms', function (Blueprint $table) {
            $table->integer('grade_number')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            // Drop old string columns if they exist
            $table->dropColumn(['city', 'school_name']);
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['school_id']);
            $table->dropColumn(['grade_number', 'city_id', 'school_id']);
            $table->string('city')->nullable();
            $table->string('school_name')->nullable();
        });
    }
};
