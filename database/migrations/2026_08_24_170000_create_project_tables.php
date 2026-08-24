<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projekte samt Typen, Meilensteinen, Positionen und Team.
 *
 * Die Anforderung fuehrt Projekte unter §61 als Zukunft. Der Auftraggeber hat
 * die Umsetzung ausdruecklich freigegeben, solange die Anwendung noch nicht
 * produktiv laeuft.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Frei definierbare Projekttypen (§61). Der Entwurf nennt Webseite,
        // Shop, Web-App, API und internes Tool nur als Beispiele.
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_label', 12)->nullable();
            $table->string('color', 20)->default('gray');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_number', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->index();

            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();

            // Freitext aus dem Panel „Status & Risiko" des Entwurfs.
            $table->text('risk_note')->nullable();

            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('deadline');
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('note')->nullable();
            $table->string('status', 20)->index();
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'due_date']);
        });

        // Positionen verweisen wahlweise auf einen Katalogartikel oder eine
        // bestehende Kundenleistung — oder auf nichts, dann sind sie frei
        // erfasst. Der Name wird immer gespeichert, damit die Position lesbar
        // bleibt, wenn der Artikel spaeter verschwindet.
        Schema::create('project_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('kind', 20)->index();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->unsignedBigInteger('unit_price_cents')->default(0);
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('project_positions');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_types');
    }
};
