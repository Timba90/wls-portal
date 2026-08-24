<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Haelt fest, welchen Katalogstand jemand zuletzt gesehen und entschieden hat.
 *
 * `catalog_snapshot` bleibt unangetastet der Stand des Verknuepfungszeitpunkts
 * (AE-6). Fuer den Abgleich braucht es einen zweiten, fortschreibbaren Stand:
 * ohne ihn haette jede Katalogaenderung dauerhaft als offen gegolten, auch
 * nachdem jemand entschieden hat, den Kundenwert bewusst zu behalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_services', function (Blueprint $table) {
            $table->json('catalog_reviewed_snapshot')->nullable()->after('catalog_snapshot');
            $table->timestamp('catalog_reviewed_at')->nullable()->after('catalog_reviewed_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('customer_services', function (Blueprint $table) {
            $table->dropColumn(['catalog_reviewed_snapshot', 'catalog_reviewed_at']);
        });
    }
};
