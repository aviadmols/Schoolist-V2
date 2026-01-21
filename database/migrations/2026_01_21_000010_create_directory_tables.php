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
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('photo_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamps();

            $table->index('classroom_id');
        });

        Schema::create('child_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('relation');
            $table->timestamps();

            $table->index('child_id');
        });

        Schema::create('important_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title');
            $table->string('phone');
            $table->timestamps();

            $table->index('classroom_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('important_contacts');
        Schema::dropIfExists('child_contacts');
        Schema::dropIfExists('children');
    }
};
