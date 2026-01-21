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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['message', 'homework', 'event'])->default('message');
            $table->string('title');
            $table->text('content')->nullable();
            $table->date('occurs_on_date')->nullable();
            $table->integer('day_of_week')->nullable(); // 0 (Sun) to 6 (Sat)
            $table->timestamps();

            $table->index('classroom_id');
            $table->index('occurs_on_date');
        });

        Schema::create('announcement_user_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_user_status');
        Schema::dropIfExists('announcements');
    }
};
