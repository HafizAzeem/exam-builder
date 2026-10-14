<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_settings', 'gemini_enabled')) {
                $table->boolean('gemini_enabled')->default(true)->after('gemini_api_key');
            }
            if (! Schema::hasColumn('ai_settings', 'groq_enabled')) {
                $table->boolean('groq_enabled')->default(true)->after('gemini_enabled');
            }
            if (! Schema::hasColumn('ai_settings', 'groq_api_key')) {
                $table->text('groq_api_key')->nullable()->after('groq_enabled');
            }
            if (! Schema::hasColumn('ai_settings', 'groq_model')) {
                $table->string('groq_model', 150)->nullable()->default('openai/gpt-oss-20b')->after('groq_api_key');
            }
            if (! Schema::hasColumn('ai_settings', 'groq_vision_model')) {
                $table->string('groq_vision_model', 150)->nullable()->default('qwen/qwen3.8-27b')->after('groq_model');
            }
            if (! Schema::hasColumn('ai_settings', 'fallback_text_provider')) {
                $table->string('fallback_text_provider', 50)->nullable()->default('gemini')->after('preferred_text_provider');
            }
        });

        if (Schema::hasColumn('ai_settings', 'fallback_text_provider')) {
            DB::table('ai_settings')->whereNull('fallback_text_provider')->update([
                'fallback_text_provider' => 'gemini',
            ]);
        }
        if (Schema::hasColumn('ai_settings', 'groq_model')) {
            DB::table('ai_settings')->whereNull('groq_model')->update([
                'groq_model' => 'openai/gpt-oss-20b',
            ]);
        }
        if (Schema::hasColumn('ai_settings', 'groq_vision_model')) {
            DB::table('ai_settings')->whereNull('groq_vision_model')->update([
                'groq_vision_model' => 'qwen/qwen3.8-27b',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $drops = [];
            foreach ([
                'gemini_enabled',
                'groq_enabled',
                'groq_api_key',
                'groq_model',
                'groq_vision_model',
                'fallback_text_provider',
            ] as $column) {
                if (Schema::hasColumn('ai_settings', $column)) {
                    $drops[] = $column;
                }
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
