<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\PortalUser;
use App\Support\Flux\FluxClient;
use App\Support\Flux\FluxException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert das PDF einer Rechnung aus Flux an den angemeldeten Kunden.
 *
 * Die Datei wird durchgereicht, nicht gespeichert. Ob die Rechnung zum Kunden
 * gehoert, prueft der Client anhand des Flux-Kontakts — eine fremde
 * Rechnungsnummer in der Adresse fuehrt zu 404.
 */
class InvoiceDownloadController extends Controller
{
    public function __invoke(Request $request, int $invoice, FluxClient $flux): Response
    {
        /** @var PortalUser $portalUser */
        $portalUser = $request->user('customer');
        $customer = $portalUser->customer;

        abort_unless($customer->isLinkedToFlux(), 404);

        try {
            $pdf = $flux->invoicePdf($invoice, $customer->flux_contact_id);
        } catch (FluxException $exception) {
            Log::warning('Rechnungsdownload im Kundenportal fehlgeschlagen.', [
                'customer_id' => $customer->id,
                'order_id' => $invoice,
                'meldung' => $exception->getMessage(),
            ]);

            abort(404, $exception->getMessage());
        }

        return response($pdf['content'], 200, [
            'Content-Type' => $pdf['mime_type'],
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
