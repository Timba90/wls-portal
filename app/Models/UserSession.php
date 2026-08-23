<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Metadaten einer Benutzersitzung fuer die Sessionverwaltung.
 *
 * Die eigentliche Session liegt in Redis; hier stehen nur die Angaben, die in
 * der Oberflaeche gezeigt werden.
 */
#[Fillable(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity'])]
class UserSession extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastActivityAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->last_activity);
    }

    public function isCurrent(): bool
    {
        return $this->getKey() === session()->getId();
    }

    /**
     * Grob lesbare Geraetebezeichnung aus dem User-Agent.
     */
    public function deviceLabel(): string
    {
        $agent = (string) $this->user_agent;

        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Macintosh') || str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unbekanntes System',
        };

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Unbekannter Browser',
        };

        return "{$browser} · {$platform}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_activity' => 'integer',
        ];
    }
}
