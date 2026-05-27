<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')
                ->nullable()
                ->after('id')
                ->constrained('institutions')
                ->nullOnDelete();

            $table->string('phone', 20)->nullable()->unique()->after('name');
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn(['phone', 'is_active']);
        });
    }
};

