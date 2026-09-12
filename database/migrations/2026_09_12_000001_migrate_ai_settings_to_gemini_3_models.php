<?php

use App\Models\AISetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace deprecated / new-user-blocked Gemini model IDs in ai_settings.
     */
    public function up(): void
    {
        $map = [
            'gemini-2.5-flash' => 'gemini-3.5-flash-lite',
            'gemini-2.5-flash-lite' => 'gemini-3.5-flash-lite',
            'gemini-2.5-pro' => 'gemini-3.5-flash',
            'gemini-2.0-flash' => 'gemini-3.5-flash-lite',
            'gemini-2.0-flash-001' => 'gemini-3.5-flash-lite',
            'gemini-2.0-flash-lite' => 'gemini-3.5-flash-lite',
            'gemini-2.0-flash-lite-001' => 'gemini-3.5-flash-lite',
            'gemini-1.5-flash' => 'gemini-3.5-flash-lite',
            'gemini-1.5-flash-001' => 'gemini-3.5-flash-lite',
            'gemini-1.5-flash-002' => 'gemini-3.5-flash-lite',
            'gemini-1.5-pro' => 'gemini-3.5-flash',
            'gemini-1.5-pro-001' => 'gemini-3.5-flash',
            'gemini-1.5-pro-002' => 'gemini-3.5-flash',
        ];

        foreach ($map as $from => $to) {
            DB::table('ai_settings')->where('model_name', $from)->update(['model_name' => $to]);
        }

        // Ensure firstOrCreate defaults stay current for new rows created later via model.
        if (AISetting::query()->doesntExist()) {
            AISetting::current();
        }
    }

    public function down(): void
    {
        // Irreversible data migration — old model IDs are unavailable to new API keys.
    }
};
