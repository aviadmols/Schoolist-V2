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
        if (config('database.default') === 'mysql') {
            DB::statement('ALTER TABLE classrooms AUTO_INCREMENT = 1000;');
        }
    }

    public function down(): void
    {
        // No down migration needed for auto-increment start
    }
};
