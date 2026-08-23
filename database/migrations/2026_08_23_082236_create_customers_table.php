<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firmen- und Privatkunden teilen sich ein Schema (Diskriminator `type`).
 *
 * Typabhaengige Pflichtfelder kann die Datenbank dadurch nicht erzwingen; das
 * uebernehmen die Actions und Formulare. Siehe AE-2 in docs/PROJECT.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number', 20)->unique();
            $table->string('type', 20)->index();

            // Firmenkunde
            $table->string('company_name')->nullable()->index();

            // Privatkunde
            $table->string('salutation', 20)->nullable();
            $table->string('academic_title', 60)->nullable();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();

            // Gemeinsam
            $table->string('short_label')->index();
            $table->string('internal_code', 32)->index();
            $table->string('status', 20)->index();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
