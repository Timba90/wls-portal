<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sessions liegen in Redis. Diese Tabelle traegt nur die Metadaten, die
        // fuer die Sessionverwaltung angezeigt werden -- der Redis-Treiber kann
        // die Sitzungen eines Benutzers nicht auflisten.
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('last_activity')->index();

            $table->index(['user_id', 'last_activity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
