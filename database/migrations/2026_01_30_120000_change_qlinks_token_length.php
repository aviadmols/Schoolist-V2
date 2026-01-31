<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow qlink tokens 4-32 chars so any /qlink/{number} works.
     * Only change column length; do not re-add unique (already exists).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE qlinks MODIFY token VARCHAR(32) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE qlinks MODIFY token VARCHAR(12) NOT NULL');
    }
};
