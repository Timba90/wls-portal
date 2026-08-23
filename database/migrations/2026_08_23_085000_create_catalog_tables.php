<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artikel-/Leistungskatalog: Kategorien, Tags, Artikel, Varianten und
 * Leistungsbestandteile.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Eine Hierarchiestufe: Kategorie und Unterkategorie. Eine Kategorie mit
        // parent_id darf selbst keine Kinder haben; geprueft in SaveCategory.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // MySQL behandelt NULL-Werte in Unique-Indizes als verschieden,
            // Hauptkategorien sind dadurch nicht abgedeckt. Die Eindeutigkeit
            // der Namen wird zusaetzlich in SaveCategory geprueft.
            $table->unique(['parent_id', 'name']);
            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('color', 20)->default('gray');
            $table->timestamps();
        });

        // Polymorph, damit Tags ohne Migration auf weitere Bereiche wie Projekte
        // oder Domains ausgedehnt werden koennen.
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');

            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('internal_name')->index();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('status', 20)->index();

            // Geldbetraege ausschliesslich als Integer in Cent.
            $table->integer('default_purchase_price_cents')->default(0);
            $table->integer('default_sales_price_cents')->default(0);

            $table->string('default_billing_interval_unit', 20);
            $table->unsignedSmallInteger('default_billing_interval_count')->nullable();

            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'category_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            // NULL bedeutet: Wert wird vom Katalogartikel uebernommen.
            $table->integer('purchase_price_cents')->nullable();
            $table->integer('sales_price_cents')->nullable();
            $table->string('billing_interval_unit', 20)->nullable();
            $table->unsignedSmallInteger('billing_interval_count')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        // Eine Tabelle fuer Katalogartikel, Varianten und Kundenleistungen: die
        // Struktur ist identisch, zwei Tabellen waeren reine Duplikation.
        Schema::create('service_components', function (Blueprint $table) {
            $table->id();
            $table->morphs('componentable');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->integer('purchase_price_cents')->nullable();
            $table->integer('sales_price_cents')->nullable();
            $table->timestamps();

            $table->index(['componentable_type', 'componentable_id', 'sort_order'], 'service_components_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_components');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
