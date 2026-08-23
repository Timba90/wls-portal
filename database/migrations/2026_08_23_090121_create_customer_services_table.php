<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundenleistung — das zentrale Modell der Anwendung.
 *
 * Eigenes Model statt Pivot-Tabelle: eine Kundenleistung darf vollstaendig vom
 * Katalog abweichen und auch ganz ohne Katalogartikel bestehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            // Die Herkunft aus dem Katalog darf nicht verlorengehen.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Katalogwerte zum Zeitpunkt der Verknuepfung. Erlaubt spaeter die
            // Unterscheidung zwischen "Kunde weicht ab" und "Katalog hat sich
            // geaendert", ohne bestehende Leistungen anzufassen.
            $table->json('catalog_snapshot')->nullable();

            $table->string('name');
            $table->string('billing_label')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->index();

            $table->integer('purchase_price_cents')->default(0);
            $table->integer('sales_price_cents')->default(0);

            $table->string('billing_interval_unit', 20)->index();
            $table->unsignedSmallInteger('billing_interval_count')->nullable();

            $table->date('service_start_date')->nullable();
            $table->date('billing_start_date')->nullable();
            $table->date('first_billing_date')->nullable();

            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Bewusst nicht abrechnen: gilt, bis die Kennzeichnung manuell
            // entfernt wird. Die Zeitpunkte halten fest, ab wann wieder normal
            // betrachtet wird -- ohne rueckwirkende Nachberechnung.
            $table->boolean('do_not_bill')->default(false)->index();
            $table->string('do_not_bill_reason', 30)->nullable();
            $table->timestamp('do_not_bill_since')->nullable();
            $table->timestamp('do_not_bill_released_at')->nullable();

            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'do_not_bill']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_services');
    }
};
