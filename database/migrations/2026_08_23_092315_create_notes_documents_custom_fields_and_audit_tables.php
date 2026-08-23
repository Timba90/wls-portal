<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uebergreifende Zusatzfunktionen: Notizen, Dokumente mit Versionierung,
 * benutzerdefinierte Felder und die Aenderungshistorie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 30)->index();
            $table->text('body');
            $table->timestamps();

            $table->index(['notable_type', 'notable_id', 'created_at'], 'notes_notable_created_index');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->string('name');
            $table->string('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id', 'created_at'], 'documents_documentable_index');
        });

        // Eine neue Datei ersetzt die alte Version nie physisch. Die hoechste
        // Versionsnummer ist automatisch die aktuelle Version.
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['document_id', 'version']);
        });

        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40)->index();
            $table->string('key', 60);
            $table->string('name');
            $table->string('type', 20);
            $table->boolean('is_required')->default(false);
            $table->text('default_value')->nullable();
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Vorgesehen fuer spaetere bedingte Sichtbarkeit. Bewusst ohne
            // Rule Builder in der Oberflaeche.
            $table->json('visibility_condition')->nullable();

            $table->timestamps();

            $table->unique(['entity_type', 'key']);
            $table->index(['entity_type', 'sort_order']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_definition_id')->constrained()->cascadeOnDelete();
            $table->morphs('customizable');

            // Ein JSON-Wert deckt alle Typen ab, auch die Mehrfachauswahl.
            $table->json('value')->nullable();

            $table->timestamps();

            $table->unique(
                ['custom_field_definition_id', 'customizable_type', 'customizable_id'],
                'custom_field_values_unique',
            );
        });

        // Aenderungshistorie. Bewusst ohne updated_at: Eintraege sind
        // unveraenderlich und ueber die Anwendung nicht loeschbar.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('auditable');
            $table->string('event', 20);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_logs_auditable_index');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('notes');
    }
};
