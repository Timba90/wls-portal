<?php

namespace App\Actions\CustomerPortal;

use App\Enums\AuditEvent;
use App\Models\PortalUser;
use App\Support\Flux\FluxClient;
use App\Support\Flux\FluxException;
use App\Support\Iban;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Erteilt ein SEPA-Lastschriftmandat aus dem Kundenportal.
 *
 * Das Mandat entsteht direkt in Flux — dort laufen die Lastschriften. Das
 * Portal speichert weder IBAN noch Mandat; es protokolliert nur, wer wann ein
 * Mandat erteilt hat.
 *
 * Gegen doppeltes Absenden schuetzt eine kurze Sperre je Kunde: zwei POSTs
 * an Flux werden nie wiederholt, also darf auch kein zweiter Klick dieselben
 * zwei POSTs ein weiteres Mal ausloesen.
 */
class SubmitSepaMandate
{
    public const TYPE_BASIC = 'BASIC';

    public const TYPE_B2B = 'B2B';

    public function __construct(private readonly FluxClient $flux) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{id: int, mandate_reference_number: string}
     */
    public function __invoke(PortalUser $portalUser, array $input): array
    {
        $customer = $portalUser->customer;

        if (! $customer->isLinkedToFlux() || ! $this->flux->isConfigured()) {
            throw ValidationException::withMessages([
                'iban' => 'Ihr Kundenkonto ist noch nicht für Lastschriften eingerichtet. Bitte wenden Sie sich an uns.',
            ]);
        }

        $input['iban'] = Iban::normalize((string) ($input['iban'] ?? ''));
        $input['bic'] = filled($input['bic'] ?? null) ? strtoupper(preg_replace('/\s+/', '', (string) $input['bic'])) : null;

        $validated = Validator::make($input, [
            'account_holder' => ['required', 'string', 'max:70'],
            'iban' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Iban::isValid((string) $value)) {
                    $fail('Die IBAN ist ungültig.');
                }
            }],
            'bic' => ['nullable', 'string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in([self::TYPE_BASIC, self::TYPE_B2B])],
            'consent' => ['accepted'],
        ], [
            'bic.regex' => 'Die BIC ist ungültig.',
            'consent.accepted' => 'Bitte bestätigen Sie das Lastschriftmandat.',
        ], [
            'account_holder' => 'Kontoinhaber',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'bank_name' => 'Bank',
            'type' => 'Mandatsart',
        ])->validate();

        if ($validated['type'] === self::TYPE_B2B && ! $customer->isCompany()) {
            throw ValidationException::withMessages([
                'type' => 'Ein Firmenlastschrift-Mandat ist nur für Firmenkunden möglich.',
            ]);
        }

        $sperre = Cache::lock('kundenportal:sepa-anlage:'.$customer->id, 60);

        if (! $sperre->get()) {
            throw ValidationException::withMessages([
                'iban' => 'Ein Mandat wird gerade übermittelt. Bitte einen Moment warten.',
            ]);
        }

        try {
            $mandat = $this->flux->createSepaMandate($customer->flux_contact_id, [
                'account_holder' => trim($validated['account_holder']),
                'iban' => $validated['iban'],
                'bic' => $validated['bic'] ?? null,
                'bank_name' => filled($validated['bank_name'] ?? null) ? trim($validated['bank_name']) : null,
                'signed_date' => now()->toDateString(),
                'type' => $validated['type'],
            ]);
        } catch (FluxException $exception) {
            throw ValidationException::withMessages(['iban' => $exception->getMessage()]);
        } finally {
            $sperre->release();
        }

        LoadSepaMandates::forget($customer);

        $customer->recordAudit(
            AuditEvent::Updated,
            description: 'SEPA-Mandat über das Kundenportal erteilt von '.$portalUser->name.' ('.$portalUser->email.'), IBAN '.FluxClient::maskIban($validated['iban']).', Mandat '.$mandat['mandate_reference_number'],
        );

        Log::info('SEPA-Mandat über das Kundenportal erteilt.', [
            'customer_id' => $customer->id,
            'portal_user_id' => $portalUser->id,
            'flux_mandate_id' => $mandat['id'],
        ]);

        return $mandat;
    }
}
