<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->text('openrouter_api_key')->nullable()->after('google_cse_id');
            $table->text('openai_api_key')->nullable()->after('openrouter_api_key');
            $table->string('preferred_text_provider', 50)->default('gemini')->after('openai_api_key');
            $table->string('openrouter_model', 150)->nullable()->after('preferred_text_provider');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn([
                'openrouter_api_key',
                'openai_api_key',
                'preferred_text_provider',
                'openrouter_model',
            ]);
        });
    }
};
