<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('purchase_cost_model')->default('per_service');
            $table->string('purchase_usage_unit')->default('user');
            $table->unsignedBigInteger('purchase_amount_cents')->default(0);
            $table->string('purchase_currency', 3)->default('EUR');
            $table->unsignedBigInteger('purchase_eur_amount_cents')->nullable();
            $table->string('purchase_interval_unit')->default('month');
            $table->unsignedInteger('purchase_interval_count')->default(1);
            $table->unsignedInteger('purchase_units_per_pack')->nullable();
        });
        Schema::table('customer_services', function (Blueprint $table): void {
            $table->unsignedInteger('usage_quantity')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('customer_services', fn (Blueprint $table) => $table->dropColumn('usage_quantity'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn([
            'purchase_cost_model', 'purchase_usage_unit', 'purchase_amount_cents', 'purchase_currency',
            'purchase_eur_amount_cents', 'purchase_interval_unit', 'purchase_interval_count', 'purchase_units_per_pack',
        ]));
    }
};
