<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexoffice_recurring_template_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexoffice_recurring_template_id')
                ->constrained(indexName: 'lexoffice_items_template_foreign')
                ->cascadeOnDelete();
            $table->uuid('lexoffice_line_item_id')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('type', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->string('unit_name', 60)->nullable();
            $table->bigInteger('unit_price_net_cents')->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('tax_rate_percentage', 5, 2)->nullable();
            $table->bigInteger('line_net_cents')->default(0);
            $table->foreignId('customer_service_id')
                ->nullable()
                ->constrained(indexName: 'lexoffice_items_service_foreign')
                ->nullOnDelete();
            $table->string('match_source', 20)->nullable();
            $table->boolean('is_ignored')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexoffice_recurring_template_items');
    }
};
