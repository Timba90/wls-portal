<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Verbindung zwischen der Adresse, unter der ein Client sich beschreibt,
 * und dem Passport-Client, den wir daraus ableiten.
 *
 * Passport speichert Kennungen als UUID; eine Adresse passt dort nicht hinein.
 * Deshalb steht sie hier, zusammen mit dem zuletzt geholten Dokument — das
 * macht nachvollziehbar, auf welcher Grundlage ein Client Zugang hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_client_documents', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500)->unique();
            $table->uuid('client_id')->index();
            $table->json('document');
            $table->timestamp('fetched_at');
            $table->timestamp('refresh_after');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_client_documents');
    }

    /**
     * Dieselbe Verbindung wie die Tabellen von Passport: die Zeilen hier
     * zeigen auf `oauth_clients`, und getrennte Verbindungen wuerden diese
     * Verbindung zerreissen.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
