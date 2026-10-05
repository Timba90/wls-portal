<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flux_ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->default('flux');
            $table->unsignedBigInteger('tenant_id');
            $table->string('number', 32);
            $table->string('name');
            $table->string('type', 20);
            $table->boolean('is_automatic')->default(false);
            $table->unsignedBigInteger('flux_id')->nullable();
            $table->string('chart', 20)->default('SKR04');
            $table->timestamp('synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider', 'tenant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flux_ledger_accounts');
    }
};
