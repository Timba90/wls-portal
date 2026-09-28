<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protokoll der DNS-Aenderungen.
 *
 * Bis hierher war der DNS-Zugriff nur lesend, und ein Protokoll waere leer
 * geblieben. Jetzt kann das Portal Eintraege aendern — und die erste Frage nach
 * einer Stoerung im Mailempfang lautet: wer hat wann was gesetzt? Diese Tabelle
 * beantwortet sie, auch wenn die Zone beim Anbieter danach wieder anders
 * aussieht.
 *
 * Der Domainname steht als Text daneben, nicht nur als Fremdschluessel: ein
 * geloeschter Datensatz darf das Protokoll nicht unlesbar machen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain_name');
            $table->string('provider', 40);

            // anlegen, aendern, loeschen
            $table->string('operation', 20);

            $table->string('record_name');
            $table->string('record_type', 20);

            /*
             * Vorher und nachher als lesbare Zeile (`www CNAME beispiel.de.`).
             * Bewusst Text und keine Felderkopie: gefragt wird „was stand da,
             * was steht jetzt da", nicht „welches Feld hat sich geaendert".
             */
            $table->text('before')->nullable();
            $table->text('after')->nullable();

            /*
             * Die Pruefsumme, mit der die Aenderung freigegeben wurde. Sie
             * verbindet den Vorgang mit dem Stand, den der Freigebende gesehen
             * hat.
             */
            $table->string('fingerprint', 64);

            /*
             * Wurde die Aenderung nach dem Schreiben in der Zone wiedergefunden?
             * Ein `false` ist der interessante Fall: der Anbieter hat den
             * Aufruf angenommen, das Ergebnis sieht aber anders aus als
             * bestellt.
             */
            $table->boolean('verified')->default(false);

            $table->text('note')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['domain_id', 'applied_at']);
            $table->index(['record_name', 'record_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_changes');
    }
};
