<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

use function Laravel\Prompts\table;

/**
 * Verwaltet die persoenlichen Tokens fuer den MCP-Zugang.
 *
 * Ein Token traegt die vollen Rechte seines Benutzers. Der Klartext wird
 * ausschliesslich beim Ausstellen angezeigt und ist danach nicht mehr
 * abrufbar — die Datenbank haelt nur den Hash.
 */
class ManageMcpTokens extends Command
{
    protected $signature = 'portal:mcp-token
        {aktion : ausstellen, auflisten oder widerrufen}
        {--email= : E-Mail-Adresse des Benutzers (bei ausstellen und auflisten)}
        {--name= : Bezeichnung des Tokens, etwa der Name des Geräts}
        {--id= : ID des zu widerrufenden Tokens}
        {--tage= : Gültigkeit in Tagen; 0 bedeutet unbegrenzt}';

    protected $description = 'Stellt Tokens für den MCP-Zugang aus, listet sie auf und widerruft sie';

    public function handle(): int
    {
        return match ($this->argument('aktion')) {
            'ausstellen' => $this->issue(),
            'auflisten' => $this->list(),
            'widerrufen' => $this->revoke(),
            default => $this->unknownAction(),
        };
    }

    private function issue(): int
    {
        $user = $this->resolveUser();

        if (! $user instanceof User) {
            return self::FAILURE;
        }

        $name = $this->option('name') ?: 'MCP-Zugang';

        $tage = $this->option('tage') !== null
            ? (int) $this->option('tage')
            : config('portal.mcp.token_expiration_days');

        $token = $user->createToken(
            $name,
            ['*'],
            $tage > 0 ? Carbon::now()->addDays($tage) : null,
        );

        $this->newLine();
        $this->info("Token für {$user->name} ({$user->email}) ausgestellt.");
        $this->newLine();
        $this->line('  Bezeichnung: '.$name);
        $this->line('  Gültig bis:  '.($tage > 0 ? Carbon::now()->addDays($tage)->format('d.m.Y') : 'unbegrenzt'));
        $this->line('  Endpunkt:    '.url(config('portal.mcp.path')));
        $this->newLine();
        $this->warn('Der folgende Wert wird nur dieses eine Mal angezeigt:');
        $this->newLine();
        $this->line('  '.$token->plainTextToken);
        $this->newLine();
        $this->line('Im MCP-Client als Header hinterlegen:');
        $this->line('  Authorization: Bearer '.$token->plainTextToken);
        $this->newLine();

        return self::SUCCESS;
    }

    private function list(): int
    {
        $query = PersonalAccessToken::query()->orderByDesc('created_at');

        if ($this->option('email')) {
            $user = $this->resolveUser();

            if (! $user instanceof User) {
                return self::FAILURE;
            }

            $query->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->getKey());
        }

        $tokens = $query->get();

        if ($tokens->isEmpty()) {
            $this->info('Keine Tokens vorhanden.');

            return self::SUCCESS;
        }

        table(
            ['ID', 'Benutzer', 'Bezeichnung', 'Zuletzt benutzt', 'Gültig bis'],
            $tokens->map(fn (PersonalAccessToken $token): array => [
                $token->id,
                $token->tokenable?->email ?? '—',
                $token->name,
                $token->last_used_at?->format('d.m.Y H:i') ?? 'nie',
                $token->expires_at?->format('d.m.Y') ?? 'unbegrenzt',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function revoke(): int
    {
        if (! $this->option('id')) {
            $this->error('Bitte die Token-ID über --id angeben. Vorhandene Tokens zeigt "auflisten".');

            return self::FAILURE;
        }

        $token = PersonalAccessToken::query()->find($this->option('id'));

        if (! $token instanceof PersonalAccessToken) {
            $this->error('Token nicht gefunden.');

            return self::FAILURE;
        }

        $bezeichnung = $token->name;
        $token->delete();

        $this->info("Token „{$bezeichnung}\" widerrufen. Weitere Aufrufe damit werden abgewiesen.");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        if (! $this->option('email')) {
            $this->error('Bitte den Benutzer über --email angeben.');

            return null;
        }

        $user = User::query()->where('email', $this->option('email'))->first();

        if (! $user instanceof User) {
            $this->error('Kein Benutzer mit dieser E-Mail-Adresse gefunden.');

            return null;
        }

        return $user;
    }

    private function unknownAction(): int
    {
        $this->error('Unbekannte Aktion. Möglich sind: ausstellen, auflisten, widerrufen.');

        return self::FAILURE;
    }
}
