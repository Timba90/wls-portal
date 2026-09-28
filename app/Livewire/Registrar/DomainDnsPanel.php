<?php

namespace App\Livewire\Registrar;

use App\Actions\Registrar\ReadDnsZone;
use App\Models\DnsChange;
use App\Models\Domain;
use App\Support\Registrar\DnsRecord;
use App\Support\Registrar\DnsZone;
use App\Support\Registrar\RegistrarClientFactory;
use App\Support\Registrar\RegistrarException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Die DNS-Zone einer Domain, gelesen beim Anbieter.
 *
 * Ausschliesslich lesend. Wer einen Eintrag aendern will, tut das beim
 * Anbieter — hier steht nur, was dort steht. Das ist Absicht und keine
 * Zwischenstufe: ein falsch gesetzter Eintrag schaltet eine Kundenseite oder
 * deren Mailempfang sofort ab.
 */
class DomainDnsPanel extends Component
{
    public Domain $domain;

    /**
     * Auf einen Eintragstyp eingeschraenkt. Leer heisst: alle.
     */
    public string $filterType = '';

    public ?string $fehler = null;

    /**
     * Beim naechsten Lesen den zwischengespeicherten Stand verwerfen.
     *
     * Kein oeffentliches Feld: es gilt nur fuer den einen Durchlauf, in dem
     * jemand „Neu laden" gedrueckt hat, und soll nicht ueber Anfragen hinweg
     * bestehen bleiben.
     */
    private bool $erneuern = false;

    private ?DnsZone $zone = null;

    private bool $gelesen = false;

    private ?bool $lesbar = null;

    public function mount(Domain $domain): void
    {
        $this->domain = $domain;
    }

    public function render(): View
    {
        $zone = $this->zone();

        return view('livewire.registrar.domain-dns-panel', [
            'zone' => $zone,
            'records' => $zone instanceof DnsZone ? $this->records($zone) : [],
            'typen' => $zone?->countsByType() ?? [],
            'lesbar' => $this->providerCanRead(),
            // Zwei verschiedene Gruende, warum hier keine Zone steht: kein
            // Anschluss, oder ein Anschluss ohne lesenden Aufruf.
            'vonHand' => ! $this->domain->provider->hasClient(),
            'aenderungen' => $this->aenderungen(),
        ]);
    }

    /**
     * Die letzten Aenderungen an dieser Zone, die aus dem Portal kamen.
     *
     * Bis hierher war das Protokoll nur beschreibbar: geaendert werden konnte
     * ueber den MCP-Server, nachsehen liess sich es nirgends. Genau danach
     * fragt aber die erste Minute einer Stoerung im Mailempfang — und eine
     * Aenderung, die der Anbieter danach anders zeigte als bestellt
     * (`verified = false`), faellt hier auf, statt in einer Tabelle zu
     * verstauben.
     *
     * @return Collection<int, DnsChange>
     */
    private function aenderungen(): Collection
    {
        return DnsChange::query()
            ->with('user')
            ->where('domain_id', $this->domain->getKey())
            ->latest('applied_at')
            ->limit(5)
            ->get();
    }

    /**
     * Liest die Zone neu, statt den vorgehaltenen Stand zu zeigen.
     */
    public function neuLaden(): void
    {
        $this->erneuern = true;
        $this->gelesen = false;
        $this->zone = null;
    }

    /**
     * Kann der Anbieter dieser Domain ueberhaupt eine Zone liefern?
     *
     * Die Frage wird vorher gestellt, damit die Oberflaeche „kann dieser
     * Anbieter nicht" sagen kann, statt einen Fehlschlag zu zeigen — das eine
     * ist ein Zustand, das andere eine Stoerung.
     */
    public function providerCanRead(): bool
    {
        // Gemerkt: die Antwort steht fest, das Erzeugen des Anschlusses liest
        // aber jedes Mal die verschluesselten Zugangsdaten aus der Datenbank.
        // Ohne Anschluss gibt es nichts zu fragen: von Hand gepflegte Domains
        // haben keinen, und die Fabrik soll dafuer auch keinen bauen.
        if (! $this->domain->provider->hasClient()) {
            return $this->lesbar = false;
        }

        return $this->lesbar ??= app(RegistrarClientFactory::class)
            ->for($this->domain->provider)
            ->canReadZone();
    }

    /**
     * Die Zone, einmal je Anfrage gelesen.
     */
    private function zone(): ?DnsZone
    {
        if ($this->gelesen) {
            return $this->zone;
        }

        $this->gelesen = true;
        $this->fehler = null;

        if (! $this->providerCanRead()) {
            return null;
        }

        try {
            $this->zone = app(ReadDnsZone::class)($this->domain, $this->erneuern);
        } catch (RegistrarException $fehler) {
            $this->fehler = $fehler->getMessage();
        }

        $this->erneuern = false;

        return $this->zone;
    }

    /**
     * @return array<int, DnsRecord>
     */
    private function records(DnsZone $zone): array
    {
        $records = $zone->sortedRecords();

        if ($this->filterType === '') {
            return $records;
        }

        return array_values(array_filter(
            $records,
            fn (DnsRecord $record): bool => $record->type === $this->filterType,
        ));
    }
}
