<?php

namespace App\Http\Middleware;

use App\Models\PortalUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Beendet die Sitzung eines gesperrten Kundenzugangs.
 *
 * Die Anmeldung prueft das bereits — diese Middleware deckt den Fall ab, dass
 * ein Zugang waehrend einer laufenden Sitzung gesperrt oder der Kunde
 * archiviert wird.
 */
class EnsurePortalUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $portalUser = Auth::guard('customer')->user();

        if ($portalUser instanceof PortalUser && ! $portalUser->canAccessPortal()) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('customer-portal.login')
                ->with('status', 'Ihr Zugang ist derzeit gesperrt. Bitte wenden Sie sich an uns.');
        }

        return $next($request);
    }
}
