# WLS Portal — Stilebene

Interne Verwaltungsoberfläche von weblab studio. Deutschsprachig, dauerhaft dunkel, Beträge in Euro.

**Was hier liegt, sind keine Komponenten.** Die Oberfläche des Portals ist serverseitig gerendert (Laravel Blade, Livewire, TallStackUI); es gibt keine JavaScript-Komponenten zum Ausliefern. Übertragen ist die Stilebene: Tokens, Schriften und die Klassensprache. Entwürfe entstehen daraus als schlichtes HTML mit diesen Klassen — was sich dann eins zu eins in eine Blade-Vorlage übernehmen lässt.

## Einbinden

Es gibt keinen Provider und kein Wrapper-Element. `styles.css` einbinden genügt; es zieht Schriften, Tokens und Klassen nach.

Die dunkle Fassung ist die Voreinstellung — anders als in der Anwendung, wo sie an `<html class="dark">` hängt. Ein Entwurf braucht dafür also nichts zu setzen. Die helle Fassung steht unter `.light` an einem Vorfahren zur Verfügung, wird im Produkt aber nicht benutzt.

## Flächen, Ränder, Text

Semantische Namen, keine Farbwerte. Von hinten nach vorn: `canvas` ist der Seitengrund, `panel` die Karte darauf, `raised` die abgesetzte Fläche darin.

| Klasse | Bedeutung | Variable |
|---|---|---|
| `bg-canvas` | Seitengrund | `--surface-canvas` |
| `bg-shell` | Kopf- und Seitenleiste | `--surface-shell` |
| `bg-panel` | Karte, Tabelle, Kachel | `--surface-panel` |
| `bg-raised` | abgesetzte Fläche in einer Karte, Tabellenkopf | `--surface-raised` |
| `bg-field` | Eingabefeld | `--surface-input` |
| `border-line` | normaler Rand, Trennlinie | `--line-subtle` |
| `border-line-strong` | betonter Rand | `--line-strong` |
| `text-ink` | Überschrift, Kennzahl | `--ink-strong` |
| `text-ink-base` | Fließtext | `--ink-base` |
| `text-ink-muted` | Nebentext, Spaltenköpfe | `--ink-muted` |
| `text-ink-faint` | Hinweis, Platzhalter | `--ink-faint` |
| `text-accent`, `bg-accent` | Minzakzent für Aktionen | `--accent` |
| `text-accent-ink` | Schrift auf der Akzentfläche | `--accent-ink` |

Die Marke der Anmeldeseite hat eigene Tokens, die dem Farbschema bewusst nicht folgen: `--brand-shell`, `--brand-panel`, `--brand-line`, `--brand-text`, `--brand-muted`, `--brand-dim`, `--brand-accent`, `--brand-accent-ink`.

## Statuspillen

Fünf Bedeutungen, immer `pill` plus eine Variante. Nichts anderes trägt Status.

`pill-ok` (läuft, aktiv) · `pill-warn` (läuft bald ab, braucht Aufmerksamkeit) · `pill-bad` (abgelaufen, fehlgeschlagen) · `pill-info` (Hinweis) · `pill-mute` (ohne Zuordnung, archiviert)

## Zahlen

`tabular` setzt IBM Plex Mono mit `tabular-nums`. **Jede Zahl im Portal trägt sie** — Beträge, Zähler, Datumsangaben in Tabellen —, damit Spalten untereinander stehen. Fließtext läuft in Source Sans 3.

Beträge deutsch: `1.234,50 €`. Datum `TT.MM.JJJJ`.

## Ein Beispiel

```html
<div class="bg-panel border-line" style="border:1px solid;border-radius:10px;padding:16px">
  <div class="text-ink-muted" style="font-size:10px;text-transform:uppercase;letter-spacing:.08em">
    Umsatz / Monat
  </div>
  <div class="tabular text-ink" style="font-size:22px;margin:6px 0">1.234,50 €</div>
  <div class="text-ink-faint" style="font-size:11px">Soll aus wiederkehrenden Leistungen</div>

  <span class="pill pill-ok" style="margin-top:10px">Aktiv</span>
</div>
```

Für das Feine — Abstände, Radien, Schriftgrade — liegt die Wahrheit in `styles.css` und den beiden Dateien unter `tokens/`. Sie sind kurz genug, um sie zu lesen.
