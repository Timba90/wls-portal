<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Client;

/**
 * Das Metadatendokument eines Clients, so wie wir es zuletzt geholt haben.
 */
#[Fillable([
    'url',
    'client_id',
    'document',
    'fetched_at',
    'refresh_after',
])]
class OauthClientDocument extends Model
{
    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Ob das Dokument frisch genug ist, um es nicht erneut zu holen.
     */
    public function isFresh(): bool
    {
        return $this->refresh_after->isFuture();
    }

    /**
     * Wie lange wir einem Dokument noch trauen, wenn es sich gerade nicht
     * holen laesst.
     *
     * Ohne diese Nachfrist risse ein Aussetzer beim Client — ein langsamer
     * Server, eine Stoerung beim CDN — jede laufende Verbindung ab, auch beim
     * blossen Erneuern eines Tokens. Mit ihr ueberbrueckt der zuletzt gueltige
     * Stand die Stoerung, ohne ihn unbegrenzt fortzuschreiben.
     */
    public function isWithinGracePeriod(): bool
    {
        $tage = (int) config('portal.mcp.oauth.client_documents.grace_days');

        return $this->fetched_at->addDays($tage)->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document' => 'array',
            'fetched_at' => 'datetime',
            'refresh_after' => 'datetime',
        ];
    }
}
