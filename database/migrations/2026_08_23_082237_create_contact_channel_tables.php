<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphe Kontaktkanaele fuer Privatkunden und Ansprechpartner.
 *
 * `is_primary` wird in einer Transaktion durch die jeweilige Action erzwungen —
 * MySQL kennt keine partiellen Unique-Indizes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_addresses', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('email')->index();
            $table->string('type', 20);
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'email']);
            $table->index(['owner_type', 'owner_id', 'sort_order']);
        });

        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('number', 60);
            $table->string('type', 20);
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'number']);
            $table->index(['owner_type', 'owner_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
        Schema::dropIfExists('email_addresses');
    }
};
