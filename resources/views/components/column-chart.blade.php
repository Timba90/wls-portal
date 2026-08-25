@props(['months', 'peak'])

{{--
    Säulendiagramm der Abrechnung je Monat.

    Bewusst aus <div> statt SVG: die Säulen sollen sich mit dem Rahmen
    dehnen, die Beschriftung soll echter Text bleiben, und die Farben
    kommen aus denselben Tokens wie der Rest.

    Für Screenreader ist ein Diagramm aus Kästen nichts. Darunter liegt
    deshalb dieselbe Reihe noch einmal als Tabelle; das Bild selbst ist
    mit aria-hidden ausgenommen, damit die Zahlen nicht doppelt kommen.
--}}
@php
    $hoechster = max(1, $peak->cents);
@endphp

<div>
    <div class="flex h-52 items-end gap-1.5 sm:gap-2" aria-hidden="true">
        @foreach ($months as $monat)
            @php
                // Auch ein sehr kleiner Monat bleibt sichtbar.
                $anteil = $monat['amount']->isZero() ? 0 : max(2, round($monat['amount']->cents / $hoechster * 100));
            @endphp

            <div class="group flex h-full min-w-0 flex-1 flex-col justify-end gap-1.5">
                {{--
                    Auf schmalen Fenstern bliebe von „1.499" nur „1.…" übrig.
                    Dann lieber nichts: die Säulenhöhe trägt den Vergleich, die
                    genauen Beträge stehen unter der Grafik.
                --}}
                <span class="tabular hidden truncate text-center text-[9.5px] leading-none text-ink-faint sm:block">
                    {{ $monat['amount']->isZero() ? '' : number_format($monat['amount']->toEuro(), 0, ',', '.') }}
                </span>

                <div class="w-full rounded-[3px] bg-[color:var(--accent)] transition group-hover:brightness-110"
                     style="height: {{ $anteil }}%"
                     title="{{ $monat['label'] }}: {{ $monat['amount']->format() }}"></div>

                <span class="truncate text-center text-[10px] leading-none text-ink-muted">
                    {{ $monat['short'] }}
                </span>
            </div>
        @endforeach
    </div>

    {{--
        Die Klasse gehört an das <div>, nicht an die Tabelle: eine Tabelle
        lässt sich nicht auf einen Pixel zusammendrücken und schöbe die Seite
        auf schmalen Fenstern quer.
    --}}
    <div class="sr-only">
        <table>
            <caption>Abrechnung je Monat</caption>
            <thead>
                <tr>
                    <th scope="col">Monat</th>
                    <th scope="col">Betrag</th>
                    <th scope="col">Fällige Leistungen</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($months as $monat)
                    <tr>
                        <th scope="row">{{ $monat['label'] }}</th>
                        <td>{{ $monat['amount']->format() }}</td>
                        <td>{{ $monat['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
