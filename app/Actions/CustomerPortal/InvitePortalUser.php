<?php

namespace App\Actions\CustomerPortal;

use App\Models\Customer;
use App\Models\PortalUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Legt einen Kundenzugang an und verschickt den Einladungslink.
 *
 * Das Passwort vergibt der Kunde selbst; der Link ist ein gewoehnliches
 * Ruecksetz-Token des Brokers `portal_users`.
 */
class InvitePortalUser
{
    /**
     * @param  array{name?: mixed, email?: mixed}  $input
     */
    public function __invoke(Customer $customer, array $input): PortalUser
    {
        if ($customer->isArchived()) {
            throw ValidationException::withMessages([
                'email' => 'Für archivierte Kunden können keine Portalzugänge angelegt werden.',
            ]);
        }

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('portal_users', 'email')],
        ], [
            'email.unique' => 'Für diese E-Mail-Adresse besteht bereits ein Portalzugang.',
        ], [
            'name' => 'Name',
            'email' => 'E-Mail-Adresse',
        ])->validate();

        $portalUser = DB::transaction(fn (): PortalUser => $customer->portalUsers()->create([
            'name' => trim($validated['name']),
            'email' => mb_strtolower(trim($validated['email'])),
            'password' => null,
            'is_active' => true,
        ]));

        $this->sendInvitation($portalUser);

        return $portalUser;
    }

    /**
     * Verschickt den Link (erneut). Auch fuer Zugaenge, die schon ein
     * Passwort haben — dann als Ruecksetzung formuliert.
     */
    public function sendInvitation(PortalUser $portalUser): void
    {
        $status = Password::broker('portal_users')->sendResetLink(['email' => $portalUser->email]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'Der Link wurde gerade erst verschickt. Bitte kurz warten.',
            ]);
        }

        $portalUser->forceFill(['invited_at' => now()])->save();
    }
}
