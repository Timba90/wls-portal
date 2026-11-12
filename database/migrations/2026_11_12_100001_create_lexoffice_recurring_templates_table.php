<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexoffice_recurring_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('lexoffice_id')->unique();
            $table->uuid('lexoffice_contact_id')->nullable()->index();
            $table->string('contact_name')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('execution_interval', 20);
            $table->string('execution_status', 20)->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('next_execution_date')->nullable();
            $table->date('last_execution_date')->nullable();
            $table->boolean('last_execution_failed')->default(false);
            $table->string('tax_type', 20)->nullable();
            $table->bigInteger('total_net_cents')->default(0);
            $table->bigInteger('total_gross_cents')->default(0);
            $table->timestamp('lexoffice_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexoffice_recurring_templates');
    }
};
