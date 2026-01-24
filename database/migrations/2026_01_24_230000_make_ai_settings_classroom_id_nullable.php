<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_settings', function () {
            DB::statement('ALTER TABLE ai_settings DROP FOREIGN KEY ai_settings_classroom_id_foreign');
            DB::statement('ALTER TABLE ai_settings MODIFY classroom_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE ai_settings ADD CONSTRAINT ai_settings_classroom_id_foreign FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_settings', function () {
            DB::statement('ALTER TABLE ai_settings DROP FOREIGN KEY ai_settings_classroom_id_foreign');
            DB::statement('ALTER TABLE ai_settings MODIFY classroom_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE ai_settings ADD CONSTRAINT ai_settings_classroom_id_foreign FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE');
        });
    }
};
