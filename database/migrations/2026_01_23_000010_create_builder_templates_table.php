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
        Schema::create('builder_templates', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->default('global');
            $table->string('type')->default('screen');
            $table->string('name');
            $table->string('key');
            $table->longText('draft_html')->nullable();
            $table->longText('published_html')->nullable();
            $table->boolean('is_override_enabled')->default(false);
            $table->json('mock_data_json')->nullable();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scope', 'key']);
            $table->index(['scope', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('builder_templates');
    }
};
