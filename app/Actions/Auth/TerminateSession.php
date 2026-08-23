<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Facades\Session;

/**
 * Beendet Sitzungen eines Benutzers.
 *
 * Die Session liegt in Redis und wird direkt über den Session-Handler
 * verworfen; die Metadatenzeile in `user_sessions` wird mit entfernt.
 */
class TerminateSession
{
    public function __invoke(User $user, string $sessionId): void
    {
        $session = UserSession::query()
            ->whereKey($sessionId)
            ->where('user_id', $user->getKey())
            ->first();

        if (! $session) {
            return;
        }

        Session::getHandler()->destroy($sessionId);

        $session->delete();
    }

    /**
     * Beendet alle Sitzungen des Benutzers außer der aktuellen.
     */
    public function allExceptCurrent(User $user): void
    {
        $currentId = Session::getId();

        $user->sessions()
            ->whereKeyNot($currentId)
            ->get()
            ->each(function (UserSession $session): void {
                Session::getHandler()->destroy($session->getKey());
                $session->delete();
            });
    }
}
