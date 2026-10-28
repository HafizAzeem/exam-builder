<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->timestamps();
        });
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label', 100);
            $table->string('label_ur', 150);
            $table->unsignedInteger('amount');
            $table->unsignedSmallInteger('months');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        DB::table('subscription_settings')->insert(['id' => 1, 'trial_days' => 14, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([
            ['key' => 'monthly', 'label' => 'Monthly', 'label_ur' => 'ماہانہ', 'amount' => 500, 'months' => 1, 'sort_order' => 1],
            ['key' => 'quarterly', 'label' => 'Three months', 'label_ur' => 'تین ماہ', 'amount' => 1350, 'months' => 3, 'sort_order' => 2],
            ['key' => 'annual', 'label' => 'Annual', 'label_ur' => 'سالانہ', 'amount' => 4500, 'months' => 12, 'sort_order' => 3],
        ] as $plan) {
            DB::table('subscription_plans')->insert($plan + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('subscription_settings');
    }
};
