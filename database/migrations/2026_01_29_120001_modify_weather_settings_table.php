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
        Schema::table('weather_settings', function (Blueprint $table) {
            $table->dropColumn(['api_key', 'city_name']);
            $table->json('temperature_ranges')->nullable()->after('icon_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weather_settings', function (Blueprint $table) {
            $table->string('api_key')->nullable()->after('api_provider');
            $table->string('city_name')->nullable()->after('api_key');
            $table->dropColumn('temperature_ranges');
        });
    }
};
