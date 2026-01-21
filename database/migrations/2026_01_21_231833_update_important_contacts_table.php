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
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->renameColumn('title', 'role');
            // Drop old name column after migration logic if needed, but for now just keep it
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('important_contacts', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'email']);
            $table->renameColumn('role', 'title');
            $table->string('name')->nullable(false)->change();
        });
    }
};
