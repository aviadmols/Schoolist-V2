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
        Schema::table('qlinks', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('created_by_user_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qlinks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classroom_id');
        });
    }
};
