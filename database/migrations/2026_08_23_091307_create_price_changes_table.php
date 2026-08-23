<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preisverlauf je Kundenleistung.
 *
 * Preise werden niemals stillschweigend ueberschrieben: jede Aenderung
 * hinterlaesst eine Zeile mit altem Preis, neuem Preis, Wirksamkeitsdatum,
 * Benutzer und Zeitpunkt. `applied_at` unterscheidet geplante von wirksamen
 * Aenderungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_service_id')->constrained()->cascadeOnDelete();
            $table->string('price_type', 20);

            // NULL beim Anlegen der Leistung -- es gab noch keinen Vorgaengerpreis.
            $table->integer('old_price_cents')->nullable();
            $table->integer('new_price_cents');

            $table->date('effective_date');
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['customer_service_id', 'price_type', 'effective_date'], 'price_changes_service_index');
            $table->index(['effective_date', 'applied_at'], 'price_changes_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_changes');
    }
};
