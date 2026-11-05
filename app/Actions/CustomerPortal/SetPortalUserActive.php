<?php

namespace App\Actions\CustomerPortal;

use App\Models\PortalUser;

/**
 * Sperrt oder entsperrt einen Kundenzugang.
 *
 * Beim Sperren wird das Remember-Token erneuert, damit ein „Angemeldet
 * bleiben" nicht ueber die Sperre hinweg traegt. Laufende Sitzungen beendet
 * die Middleware beim naechsten Aufruf.
 */
class SetPortalUserActive
{
    public function __invoke(PortalUser $portalUser, bool $active): PortalUser
    {
        $portalUser->forceFill([
            'is_active' => $active,
            'remember_token' => $active ? $portalUser->remember_token : null,
        ])->save();

        return $portalUser;
    }
}
