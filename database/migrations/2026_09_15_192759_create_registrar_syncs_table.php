<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protokoll der Bestandsabgleiche.
 *
 * Ein Abgleich, der nachts still scheitert, ist schlimmer als keiner: der
 * Bestand sieht aus wie am Vortag, und niemand erfaehrt es. Jeder Lauf traegt
 * sich deshalb hier ein — mit Ergebnis oder mit der Meldung des Anbieters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrar_syncs', function (Blueprint $table): void {
            $table->id();

            $table->string('provider');

            // Von Hand oder aus dem Zeitplan.
            $table->string('trigger')->default('scheduled');

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->unsignedInteger('domains_new')->default(0);
            $table->unsignedInteger('domains_updated')->default(0);
            $table->unsignedInteger('certificates_new')->default(0);
            $table->unsignedInteger('certificates_updated')->default(0);
            $table->unsignedInteger('skipped')->default(0);

            // Leer heisst: durchgelaufen.
            $table->text('error')->nullable();

            $table->timestamps();

            // Die Oberflaeche fragt immer nach dem letzten Lauf je Anbieter.
            $table->index(['provider', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrar_syncs');
    }
};
