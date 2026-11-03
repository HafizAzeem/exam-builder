<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_logs')->orderBy('id')->chunkById(500, function ($logs) {
            $names = DB::table('users')->whereIn('id', $logs->pluck('user_id'))->pluck('name', 'id');
            foreach ($logs as $log) {
                $meta = json_decode($log->meta ?? '{}', true) ?: [];
                if (! isset($meta['actor_name']) && isset($names[$log->user_id])) {
                    $meta['actor_name'] = $names[$log->user_id];
                    DB::table('activity_logs')->where('id', $log->id)->update(['meta' => json_encode($meta)]);
                }
            }
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Retain nullable actors: restoring cascade deletion would erase audit history.
    }
};
