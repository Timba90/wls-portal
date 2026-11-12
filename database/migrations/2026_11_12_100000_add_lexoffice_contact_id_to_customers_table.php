<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            // Ein Lexoffice-Kontakt gehoert hoechstens zu einem Portal-Kunden.
            $table->uuid('lexoffice_contact_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['lexoffice_contact_id']);
            $table->dropColumn('lexoffice_contact_id');
        });
    }
};
