<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('label_ur');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('title_ur');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
        });

        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('region')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('boards')->insert([
            'name' => 'Lahore Board',
            'region' => 'Punjab',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Keep paper-builder behaviour: only Class 9 is live until Super Admin activates others.
        if (Schema::hasTable('grades')) {
            DB::table('grades')->where('number', '!=', 9)->update(['is_active' => false]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boards');

        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
