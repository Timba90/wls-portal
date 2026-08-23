<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transaktionssichere Nummernvergabe.
 *
 * Bewusst eine eigene Tabelle statt MAX(nummer) + 1: eine einmal vergebene
 * Nummer darf niemals erneut vergeben werden, auch dann nicht, wenn der
 * zugehoerige Datensatz archiviert wurde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
