<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_settings', 'langsearch_api_key')) {
                $table->text('langsearch_api_key')->nullable()->after('groq_vision_model');
            }
            if (! Schema::hasColumn('ai_settings', 'searxng_url')) {
                $table->string('searxng_url', 255)->nullable()->after('langsearch_api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $drops = [];
            foreach (['langsearch_api_key', 'searxng_url'] as $column) {
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
