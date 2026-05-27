<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_papers', function (Blueprint $table) {
            $table->json('institute_snapshot')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('saved_papers', function (Blueprint $table) {
            $table->dropColumn('institute_snapshot');
        });
    }
};

