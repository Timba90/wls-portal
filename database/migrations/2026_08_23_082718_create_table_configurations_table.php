<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabellenkonfiguration: sichtbare Spalten, Reihenfolge und Breite.
 *
 * Die Konfiguration gilt global und ausdruecklich nicht benutzerspezifisch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('table_key', 64)->unique();
            $table->json('columns');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_configurations');
    }
};
