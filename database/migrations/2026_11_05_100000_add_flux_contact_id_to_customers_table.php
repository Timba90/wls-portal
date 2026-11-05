<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verknüpfung eines Kunden mit seinem Kontakt in Flux.
     *
     * Rechnungen und SEPA-Mandate liegen in Flux; ohne diese Kennung kann das
     * Kundenportal sie keinem Kunden zuordnen.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedBigInteger('flux_contact_id')->nullable()->unique()->after('internal_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['flux_contact_id']);
            $table->dropColumn('flux_contact_id');
        });
    }
};
