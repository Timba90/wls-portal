<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Betriebsstatus je Projekt und ein Symbol am Projekttyp.
 *
 * Backup, Sicherheit und Aktualisierungen werden von Hand gepflegt. Es gibt
 * keine Ueberwachung, aus der sie sich speisen koennten — Hosting-Systeme und
 * Backup-Oberflaeche sind laut Anforderung Zukunft. Ein von Hand gepflegter
 * Stand ist ehrlicher als eine erfundene Automatik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_types', function (Blueprint $table) {
            // Schluessel eines Markenzeichens, zum Beispiel „laravel".
            $table->string('icon', 40)->nullable()->after('short_label');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('backup_status', 20)->default('unknown')->after('risk_note');
            $table->string('security_status', 20)->default('unknown')->after('backup_status');
            $table->string('update_status', 20)->default('unknown')->after('security_status');
            $table->date('operations_checked_on')->nullable()->after('update_status');
        });

        $this->seedFixedProjectTypes();
    }

    /**
     * Die drei festen Projekttypen.
     *
     * Als Migration und nicht nur als Seeder: der Seeder laeuft nur bei einer
     * frischen Datenbank, die Typen sollen aber auch in einem bestehenden
     * Bestand vorhanden sein. `updateOrCreate` laesst eine vorhandene Zeile
     * gleichen Namens stehen und ergaenzt nur Symbol und Farbe.
     */
    private function seedFixedProjectTypes(): void
    {
        $typen = [
            ['Laravel', 'LAR', 'laravel', 'red'],
            ['Shopify', 'SHOP', 'shopify', 'green'],
            ['WordPress', 'WP', 'wordpress', 'blue'],
        ];

        foreach ($typen as $reihenfolge => [$name, $kuerzel, $symbol, $farbe]) {
            $werte = [
                'short_label' => $kuerzel,
                'icon' => $symbol,
                'color' => $farbe,
                'sort_order' => $reihenfolge,
                'is_active' => true,
                'updated_at' => now(),
            ];

            $vorhanden = DB::table('project_types')->where('name', $name);

            // Ein bereits angelegter Typ behaelt sein Anlagedatum — er wird
            // hier ergaenzt, nicht neu angelegt.
            if ($vorhanden->exists()) {
                $vorhanden->update($werte);

                continue;
            }

            DB::table('project_types')->insert($werte + [
                'name' => $name,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('project_types', function (Blueprint $table) {
            $table->dropColumn('icon');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['backup_status', 'security_status', 'update_status', 'operations_checked_on']);
        });
    }
};
