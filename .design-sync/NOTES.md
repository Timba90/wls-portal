# Notizen zum Design-Sync

## Warum nur die Stilebene

Das WLS Portal ist kein JS-Designsystem. Die Oberfläche ist serverseitig gerendert — Blade,
Livewire, TallStackUI —, es gibt kein `dist/`, kein Storybook und keine React-Komponenten.
Claude Design rendert aber kompilierte JS-Komponenten aus `window.<name>.*`. Ein vollwertiger
Sync hieße, die Blade-Komponenten in React nachzubauen; das widerspricht dem Grundsatz der
Anleitung („ship what the customer already built, never a reimplementation") und erzeugte eine
zweite Fassung der Oberfläche, die mitgepflegt werden müsste.

Auf Ansage des Auftraggebers wird deshalb nur die Stilebene übertragen: Tokens, Schriften und
die Klassensprache. Entwürfe entstehen daraus als HTML mit denselben Klassennamen, die eine
Blade-Vorlage benutzt — sie lassen sich also übernehmen, statt übersetzt zu werden.

## Der Konverter der Anleitung wird nicht benutzt

`package-build.mjs` bündelt ein kompiliertes `dist/`. Hier gibt es keins, also erzeugt
`build-style-layer.mjs` die Ausgabe direkt. Kein `_ds_bundle.js`, kein `_ds_sync.json` — ohne
Komponenten gäbe es nichts zu verankern, und ein Anker, der nichts belegt, wäre schlimmer als
keiner: der nächste Lauf prüft einfach alles neu, was hier richtig ist.

## Zwei Dinge, die beim Messen aufgefallen sind

1. **Dunkel muss die Voreinstellung sein.** In der Anwendung hängt die dunkle Fassung an
   `<html class="dark">`. Ein Entwurf setzt diese Klasse nicht und bekäme sonst die helle
   Fassung — also nicht das Produkt. `tokens.css` gibt die dunklen Werte deshalb zusätzlich
   auf `:root` aus; die helle Fassung steht unter `.light`. Beides erzeugt, nicht abgetippt.
2. **`--font-mono` steht im Tailwind-Block**, nicht bei den Tokens. Ohne ihn liefe `.tabular`
   in der Fließtextschrift, und im Portal trägt jede Zahl diese Klasse. Die Schriftfamilien
   werden jetzt eigens mitgenommen.

Beides fiel nur auf, weil die Ausgabe im Browser gemessen wurde (`.design-sync/probe.html`,
Computed Styles). Die Seite bleibt im Repo und wird nicht hochgeladen.

## Offen: das Hochladen

In dieser Sitzung fehlt die Berechtigung für claude.ai/design:

> DesignSync needs design-system authorization, and `/design-login` cannot run in this
> non-interactive session.

Nötig ist einmalig `/design-login` aus einer interaktiven Claude-Code-Sitzung auf diesem
Rechner. Danach: Projekt anlegen, dessen `projectId` hier in `config.json` eintragen und
`ds-bundle/` hochladen (`styles.css`, `tokens/**`, `fonts/**`, `README.md`).
