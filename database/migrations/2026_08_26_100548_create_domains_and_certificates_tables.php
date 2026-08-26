<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domains und Zertifikate als eigener Bestand.
 *
 * Beides sind technische Objekte mit eigenen Terminen: eine Domain laeuft ab,
 * ein Zertifikat auch, und beide gehoeren zu einem Registrar. Die Abrechnung
 * haengt daran nur lose — ueber die Kundenleistung, die sie traegt. Getrennt
 * gehalten, weil ein Ablaufdatum kein Preis ist und umgekehrt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();

            // Der vollstaendige Name ist der fachliche Schluessel; eine Domain
            // gibt es weltweit nur einmal.
            $table->string('name')->unique();

            $table->string('provider', 40);

            // Die Kennung beim Registrar, soweit er eine vergibt. Sie ist der
            // sichere Anker beim erneuten Abgleich, wenn der Name sich aendert.
            $table->string('provider_reference')->nullable();

            $table->string('status', 40)->default('unknown');
            $table->date('registered_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->boolean('auto_renew')->default(false);

            /** @var array<int, string> Nameserver in der Reihenfolge des Registrars. */
            $table->json('nameservers')->nullable();

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // Die Referenz ist der Anker beim Abgleich; sie muss je Anbieter
            // eindeutig sein, sonst wäre der Abgleich nicht bestimmt. Mehrere
            // NULL bleiben erlaubt — nicht jeder Anbieter vergibt eine.
            $table->unique(['provider', 'provider_reference']);
            $table->index(['provider', 'expires_on']);
            $table->index('customer_id');
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();

            $table->string('common_name');
            $table->string('provider', 40);
            $table->string('provider_reference')->nullable();

            $table->string('status', 40)->default('unknown');
            $table->string('issuer')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();

            /** @var array<int, string> Weitere abgedeckte Namen. */
            $table->json('alternative_names')->nullable();

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // Ein Zertifikat kann fuer denselben Namen mehrfach ausgestellt
            // werden; eindeutig ist erst die Kennung beim Anbieter.
            $table->unique(['provider', 'provider_reference']);
            $table->index(['provider', 'expires_on']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('domains');
    }
};
