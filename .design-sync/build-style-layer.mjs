/**
 * Baut die Stilebene des WLS Portals für Claude Design.
 *
 * Kein Komponenten-Bundle: die Oberfläche ist serverseitig gerendert (Blade,
 * Livewire, TallStackUI), es gibt keine JS-Komponenten zum Ausliefern. Was
 * übertragbar ist, ist die Stilebene — Tokens, Schriften und die
 * Klassensprache, mit der die Anwendung ihre Flächen benennt.
 *
 * Alles hier stammt aus dem Repo selbst: die Tokens wörtlich aus
 * `resources/css/app.css`, die Schriften aus dem Vite-Build. Nichts wird
 * abgetippt, damit die Ausgabe nicht auseinanderläuft.
 */
import { readFileSync, writeFileSync, mkdirSync, copyFileSync, readdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';

const AUS = 'ds-bundle';
const QUELLE = 'resources/css/app.css';
const BUILD = 'public/build/assets';

rmSync(AUS, { recursive: true, force: true });
mkdirSync(join(AUS, 'tokens'), { recursive: true });
mkdirSync(join(AUS, 'fonts'), { recursive: true });

const css = readFileSync(QUELLE, 'utf8');

/** Einen Block `selektor { … }` wörtlich aus dem Stylesheet holen. */
function block(selektor, ab = 0) {
    const start = css.indexOf(selektor + ' {', ab);
    if (start === -1) throw new Error(`Block ${selektor} nicht gefunden`);
    const ende = css.indexOf('\n}', start);
    if (ende === -1) throw new Error(`Block ${selektor} nicht geschlossen`);
    return css.slice(start, ende + 2);
}

const ersterRoot = block(':root');
const dunkel = block('.dark');
const marke = block(':root', css.indexOf(dunkel) + dunkel.length);

/** Nur die Schriftfamilien aus dem Tailwind-Block — `.tabular` braucht sie. */
function schriftfamilien() {
    const zeilen = css.split('\n').filter((z) => /^\s+--font-(sans|mono):/.test(z));
    if (zeilen.length < 2) throw new Error('Schriftfamilien nicht gefunden');

    // Die Sans-Angabe läuft über zwei Zeilen.
    const start = css.indexOf(zeilen[0]);
    const ende = css.indexOf(';', css.indexOf(zeilen[1]));

    return css.slice(start, ende + 1);
}

/** Denselben Rumpf unter einem anderen Selektor ausgeben. */
function alsSelektor(quelle, selektor) {
    return selektor + ' {' + quelle.slice(quelle.indexOf('{') + 1);
}

writeFileSync(join(AUS, 'tokens', 'tokens.css'), `/*
 * Design-Tokens des WLS Portals — wörtlich aus resources/css/app.css.
 *
 * Ein Unterschied zur Anwendung, mit Absicht: dort hängt die dunkle Fassung an
 * <html class="dark">, hier trägt sie :root. Die Oberfläche läuft dauerhaft
 * dunkel, und ein Entwurf, der die Klasse nicht setzt, bekäme sonst die helle
 * Fassung — also nicht das Produkt. Die helle Fassung bleibt unter .light
 * erreichbar, .dark funktioniert weiterhin.
 */

:root {
${schriftfamilien()}
}

/* Die dunkle Fassung ist die Voreinstellung. */
${alsSelektor(dunkel, ':root')}

${dunkel}

/* Die helle Fassung des Entwurfs — nur, wenn sie ausdrücklich verlangt wird. */
${alsSelektor(ersterRoot, '.light')}

/* Markenfläche der Anmeldeseite — bewusst unabhängig vom Farbschema. */
${marke}
`);

/*
 * Die Anwendung erzeugt ihre Flächenklassen über Tailwind (`@theme inline`).
 * In Claude Design gibt es diesen Build nicht, deshalb hier dieselben Namen
 * als einfaches CSS auf denselben Tokens — damit ein Entwurf und die
 * Blade-Vorlage dieselbe Sprache sprechen.
 */
const flaechen = {
    canvas: '--surface-canvas', shell: '--surface-shell', panel: '--surface-panel',
    raised: '--surface-raised', field: '--surface-input',
};
const texte = {
    ink: '--ink-strong', 'ink-base': '--ink-base', 'ink-muted': '--ink-muted', 'ink-faint': '--ink-faint',
    accent: '--accent', 'accent-ink': '--accent-ink',
};
const raender = { line: '--line-subtle', 'line-strong': '--line-strong' };

const zeilen = [
    '/*',
    ' * Klassensprache der Anwendung, als einfaches CSS.',
    ' *',
    ' * Dieselben Namen wie im Tailwind-Build des Portals, auf dieselben Tokens',
    ' * gesetzt. Ein Entwurf, der `bg-panel text-ink-muted border-line` schreibt,',
    ' * lässt sich eins zu eins in eine Blade-Vorlage übernehmen.',
    ' */',
    '',
];

for (const [name, token] of Object.entries(flaechen)) {
    zeilen.push(`.bg-${name} { background-color: var(${token}); }`);
}
zeilen.push('');
for (const [name, token] of Object.entries(texte)) {
    zeilen.push(`.text-${name} { color: var(${token}); }`);
}
zeilen.push('');
for (const [name, token] of Object.entries(raender)) {
    zeilen.push(`.border-${name} { border-color: var(${token}); }`);
}
zeilen.push('');
zeilen.push('.bg-accent { background-color: var(--accent); }');
zeilen.push('.bg-accent-hover:hover { background-color: var(--accent-hover); }');
zeilen.push('');
zeilen.push('/* Zahlen und Kennzahlen laufen durchgängig in der Mono-Schrift. */');
zeilen.push(block('.tabular'));
zeilen.push('');
zeilen.push('/* Statuspille — fünf Bedeutungen, je Fläche, Rand und Schrift. */');
zeilen.push(block('.pill'));
zeilen.push('');
for (const art of ['ok', 'warn', 'bad', 'info', 'mute']) {
    const treffer = css.match(new RegExp(`^\\.pill-${art} \\{.*$`, 'm'));
    if (!treffer) throw new Error(`.pill-${art} nicht gefunden`);
    zeilen.push(treffer[0]);
}

writeFileSync(join(AUS, 'tokens', 'utilities.css'), zeilen.join('\n') + '\n');

// Schriften: die gebauten Dateien und ihr Stylesheet, Pfade auf ./ umgestellt.
const schriftCss = readdirSync(BUILD).find((d) => d.startsWith('fonts-') && d.endsWith('.css'));
if (!schriftCss) throw new Error('Kein fonts-*.css im Build — bitte npm run build ausführen.');

const dateien = readdirSync(BUILD).filter((d) => /\.(woff2?)$/.test(d));
for (const datei of dateien) {
    copyFileSync(join(BUILD, datei), join(AUS, 'fonts', datei));
}

writeFileSync(
    join(AUS, 'fonts', 'fonts.css'),
    readFileSync(join(BUILD, schriftCss), 'utf8').replaceAll('/build/assets/', './'),
);

writeFileSync(join(AUS, 'styles.css'), `/*
 * Stilebene des WLS Portals.
 *
 * Ein Entwurf erhält genau diese Datei und alles, was sie einbindet.
 */
@import './fonts/fonts.css';
@import './tokens/tokens.css';
@import './tokens/utilities.css';

/* Die Oberfläche läuft dauerhaft dunkel. */
:root {
    color-scheme: dark;
}

body {
    margin: 0;
    background-color: var(--surface-canvas);
    color: var(--ink-base);
    font-family: 'Source Sans 3', ui-sans-serif, system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
}
`);

/*
 * README: der von Hand gepflegte Konventionstext, davor gesetzt, plus ein
 * erzeugtes Verzeichnis der Dateien. Der Text wird nie überschrieben — er
 * gehört seinen Autoren, das Verzeichnis dem Build.
 */
const kopf = readFileSync('.design-sync/conventions.md', 'utf8').trimEnd();
const schriftschnitte = (readFileSync(join(AUS, 'fonts', 'fonts.css'), 'utf8').match(/@font-face/g) || []).length;

writeFileSync(join(AUS, 'README.md'), `${kopf}

---

## Was hier liegt

| Datei | Inhalt |
|---|---|
| \`styles.css\` | Einstieg. Bindet die drei anderen ein und setzt Grund, Textfarbe und Schrift. |
| \`tokens/tokens.css\` | ${(dunkel.match(/--/g) || []).length} Tokens für Flächen, Ränder, Text, Akzent und Statuspillen, dazu die Markenfläche. Dunkel als Voreinstellung, hell unter \`.light\`. |
| \`tokens/utilities.css\` | Die Klassensprache als einfaches CSS: Flächen, Ränder, Text, Statuspillen, \`tabular\`. |
| \`fonts/\` | Source Sans 3 (400/500/600/700) und IBM Plex Mono (400/500/600), selbst gehostet — ${schriftschnitte} Schnitte, ${dateien.length} Dateien. |

Erzeugt aus dem Repo \`wls-portal\` mit \`node .design-sync/build-style-layer.mjs\`. Die Tokens stammen wörtlich aus \`resources/css/app.css\`, die Schriften aus dem Vite-Build — nichts davon ist abgetippt.

Die Komponenten selbst liegen als Blade-Vorlagen im Repo (\`resources/views/components/\`) und werden bewusst nicht nachgebaut.
`);

console.log(JSON.stringify({
    tokens: (ersterRoot.match(/--/g) || []).length,
    schriftdateien: dateien.length,
    schriftschnitte,
}, null, 1));
