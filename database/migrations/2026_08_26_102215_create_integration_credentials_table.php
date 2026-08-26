<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zugangsdaten der Schnittstellen, verschluesselt abgelegt (§50).
 *
 * Ein Datensatz je Anbieter. Die Werte liegen als verschluesseltes JSON in
 * einer einzigen Spalte: welche Felder ein Anbieter braucht, weiss der
 * Anschluss, nicht die Tabelle. So kommt ein neuer Anbieter ohne Migration aus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->unique();

            // Verschluesselt; deshalb `text` und kein `json`.
            $table->text('credentials')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_credentials');
    }
};
