<?php

namespace App\Actions\Contacts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Gleicht E-Mail-Adressen und Telefonnummern eines Kontaktkanal-Inhabers ab.
 *
 * Verwendet von Privatkunden und Ansprechpartnern.
 *
 * Stellt sicher, dass jeweils genau eine Adresse beziehungsweise Nummer primaer
 * ist. Da MySQL keine partiellen Unique-Indizes kennt, wird diese Regel hier in
 * einer Transaktion durchgesetzt.
 *
 * @phpstan-type ChannelInput array{email?: string, number?: string, type: string, is_primary?: bool}
 */
class SyncContactChannels
{
    /**
     * @param  array<int, array{email: string, type: string, is_primary?: bool}>  $emails
     * @param  array<int, array{number: string, type: string, is_primary?: bool}>  $phones
     */
    public function __invoke(Model $owner, array $emails, array $phones): void
    {
        DB::transaction(function () use ($owner, $emails, $phones): void {
            $this->syncEmails($owner, $emails);
            $this->syncPhones($owner, $phones);
        });

        $owner->unsetRelation('emailAddresses')->unsetRelation('phoneNumbers');
    }

    /**
     * @param  array<int, array{email: string, type: string, is_primary?: bool}>  $emails
     */
    private function syncEmails(Model $owner, array $emails): void
    {
        $emails = $this->normalise($emails, 'email');

        $owner->emailAddresses()
            ->whereNotIn('email', array_column($emails, 'email'))
            ->delete();

        foreach ($emails as $index => $email) {
            $owner->emailAddresses()->updateOrCreate(
                ['email' => $email['email']],
                [
                    'type' => $email['type'],
                    'is_primary' => $email['is_primary'],
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * @param  array<int, array{number: string, type: string, is_primary?: bool}>  $phones
     */
    private function syncPhones(Model $owner, array $phones): void
    {
        $phones = $this->normalise($phones, 'number');

        $owner->phoneNumbers()
            ->whereNotIn('number', array_column($phones, 'number'))
            ->delete();

        foreach ($phones as $index => $phone) {
            $owner->phoneNumbers()->updateOrCreate(
                ['number' => $phone['number']],
                [
                    'type' => $phone['type'],
                    'is_primary' => $phone['is_primary'],
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * Entfernt leere Eintraege und Dubletten und sorgt fuer genau einen
     * Primaereintrag: die erste als primaer markierte Zeile gewinnt, ist keine
     * markiert, wird es die erste Zeile.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalise(array $rows, string $valueKey): array
    {
        $rows = collect($rows)
            ->map(fn (array $row): array => [
                ...$row,
                $valueKey => trim((string) ($row[$valueKey] ?? '')),
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ])
            ->filter(fn (array $row): bool => $row[$valueKey] !== '')
            ->unique($valueKey)
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $primaryIndex = $rows->search(fn (array $row): bool => $row['is_primary']);

        if ($primaryIndex === false) {
            $primaryIndex = 0;
        }

        return $rows
            ->map(fn (array $row, int $index): array => [...$row, 'is_primary' => $index === $primaryIndex])
            ->all();
    }
}
