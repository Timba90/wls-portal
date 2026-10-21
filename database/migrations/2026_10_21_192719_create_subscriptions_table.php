<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('server', 40);
            $table->string('name', 253)->collation('utf8mb4_bin');
            $table->unsignedBigInteger('plesk_id')->nullable();
            $table->string('status', 40)->default('active');
            $table->string('hosting_login', 128)->nullable();
            $table->string('service_plan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->date('expires_on')->nullable();
            $table->text('description')->nullable();
            $table->string('subscriber')->nullable();
            $table->date('setup_date')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['server', 'name']);
            $table->unique(['server', 'plesk_id']);
            $table->index(['status', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
