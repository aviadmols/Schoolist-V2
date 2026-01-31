<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow qlink tokens 4-32 chars so any /qlink/{number} works.
     */
    public function up(): void
    {
        Schema::table('qlinks', function (Blueprint $table) {
            $table->string('token', 32)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qlinks', function (Blueprint $table) {
            $table->string('token', 12)->unique()->change();
        });
    }
};
