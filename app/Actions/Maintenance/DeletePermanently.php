<?php

namespace App\Actions\Maintenance;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\HasTags;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\EmailAddress;
use App\Models\PhoneNumber;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Entfernt einen Datensatz endgueltig aus der Datenbank.
 *
 * Die regulaere Anwendung archiviert ausschliesslich. Dieser Weg existiert fuer
 * den MCP-Zugang und raeumt alles mit ab, was ueber polymorphe Beziehungen an
 * dem Datensatz haengt und deshalb keine Fremdschluesselregel besitzt.
 *
 * Eintraege der Aenderungshistorie bleiben bestehen: sie sind unveraenderlich
 * und dokumentieren gerade auch diesen Vorgang.
 */
class DeletePermanently
{
    /**
     * @return array<string, int> Anzahl der entfernten Datensaetze je Bereich
     */
    public function __invoke(Model $model): array
    {
        return DB::transaction(function () use ($model): array {
            $entfernt = $this->purgeDependents($model);

            $entfernt += $this->purgePolymorphicChildren($model);

            $model->delete();

            return array_filter($entfernt, fn (int $anzahl): bool => $anzahl > 0);
        });
    }

    /**
     * Datensaetze, die per Fremdschluessel an diesem Model haengen und dessen
     * Loeschung sonst blockieren wuerden.
     *
     * @return array<string, int>
     */
    private function purgeDependents(Model $model): array
    {
        if ($model instanceof Customer) {
            $leistungen = 0;

            // Einzeln, damit jede Leistung ihre eigenen Anhaenge mitnimmt und
            // ihr Loeschen in der Historie erscheint.
            $model->services()->cursor()->each(function (CustomerService $leistung) use (&$leistungen): void {
                $this->__invoke($leistung);
                $leistungen++;
            });

            return ['leistungen' => $leistungen];
        }

        if ($model instanceof Product) {
            // Kundenleistungen verlieren nur ihren Katalogbezug, der Snapshot
            // bewahrt die Herkunft. Varianten haengen am Artikel selbst.
            return ['varianten' => $model->variants()->count()];
        }

        return [];
    }

    /**
     * Notizen, Dokumente, benutzerdefinierte Felder, Tags und Kontaktkanaele.
     *
     * @return array<string, int>
     */
    private function purgePolymorphicChildren(Model $model): array
    {
        $entfernt = [];
        $traits = class_uses_recursive($model);

        if (in_array(HasNotes::class, $traits, true)) {
            $entfernt['notizen'] = $model->notes()->delete();
        }

        if (in_array(HasDocuments::class, $traits, true)) {
            $entfernt['dokumente'] = $this->purgeDocuments($model);
        }

        if (in_array(HasCustomFields::class, $traits, true)) {
            $entfernt['benutzerdefinierte_felder'] = $model->customFieldValues()->delete();
        }

        if (in_array(HasTags::class, $traits, true)) {
            $entfernt['tag_zuordnungen'] = $model->tags()->detach();
        }

        $entfernt['email_adressen'] = EmailAddress::query()
            ->where('owner_type', $model->getMorphClass())
            ->where('owner_id', $model->getKey())
            ->delete();

        $entfernt['telefonnummern'] = PhoneNumber::query()
            ->where('owner_type', $model->getMorphClass())
            ->where('owner_id', $model->getKey())
            ->delete();

        return $entfernt;
    }

    /**
     * Entfernt Dokumente samt Versionen und den zugehoerigen Dateien.
     */
    private function purgeDocuments(Model $model): int
    {
        $anzahl = 0;

        $model->documents()->with('versions')->cursor()->each(function (Document $dokument) use (&$anzahl): void {
            $dokument->versions->each(function (DocumentVersion $version): void {
                // Erst nach dem Commit der aeussersten Transaktion. Direkt
                // geloescht waeren die Dateien bei einem spaeteren Rollback
                // weg, waehrend die Datenbankzeilen bestehen blieben.
                DB::afterCommit(function () use ($version): void {
                    Storage::disk($version->disk)->delete($version->path);
                });
            });

            // Die Versionszeilen haengen per Fremdschluessel am Dokument und
            // verschwinden dadurch mit.
            $dokument->delete();
            $anzahl++;
        });

        return $anzahl;
    }
}
