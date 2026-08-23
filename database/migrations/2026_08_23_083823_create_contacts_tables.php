<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ansprechpartner und ihre Zuordnung zu Firmenkunden.
 *
 * Ein Ansprechpartner traegt kein eigenes Firmenfeld — die Verbindung entsteht
 * ausschliesslich ueber `contact_assignments`. Rollen, Priorität und
 * Primaerkontakte gelten je Zuordnung, nicht je Ansprechpartner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('salutation', 20)->nullable();
            $table->string('academic_title', 60)->nullable();
            $table->string('first_name', 120);
            $table->string('last_name', 120)->index();
            $table->string('gender', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('preferred_contact_method', 20)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });

        Schema::create('contact_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contact_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_billing_contact')->default(false);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->string('preferred_contact_method', 20)->nullable();

            // Je Kundenzuordnung kann eine abweichende primaere E-Mail-Adresse
            // beziehungsweise Telefonnummer gelten.
            $table->foreignId('primary_email_id')->nullable()->constrained('email_addresses')->nullOnDelete();
            $table->foreignId('primary_phone_id')->nullable()->constrained('phone_numbers')->nullOnDelete();

            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['contact_id', 'customer_id']);
            $table->index(['customer_id', 'is_active', 'priority']);
        });

        Schema::create('contact_assignment_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_role_id')->constrained()->cascadeOnDelete();

            $table->unique(['contact_assignment_id', 'contact_role_id'], 'contact_assignment_role_unique');
        });

        // Vertretungen je Kunde und Rolle, mit Priorität.
        Schema::create('contact_deputies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestamps();

            $table->unique(['customer_id', 'contact_role_id', 'contact_id'], 'contact_deputies_unique');
            $table->index(['customer_id', 'contact_role_id', 'priority'], 'contact_deputies_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_deputies');
        Schema::dropIfExists('contact_assignment_role');
        Schema::dropIfExists('contact_assignments');
        Schema::dropIfExists('contact_roles');
        Schema::dropIfExists('contacts');
    }
};
