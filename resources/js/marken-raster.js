/**
 * Raster hinter der Markenspalte der Anmeldeseite.
 *
 * Faehrt die Maus ueber die Flaeche, leuchtet die Zelle unter dem Zeiger auf
 * und verblasst danach wieder. Gezeichnet wird nur, solange ueberhaupt eine
 * Zelle sichtbar ist — ohne Mausbewegung laeuft keine Animationsschleife.
 *
 * Der Effekt ist reine Dekoration: das Canvas liegt hinter dem Inhalt, nimmt
 * keine Zeigerereignisse an und bleibt bei `prefers-reduced-motion: reduce`
 * vollstaendig aus.
 */

/** Kantenlaenge einer Rasterzelle in CSS-Pixeln. */
const ZELLE = 80;

/** Wie lange eine beruehrte Zelle voll leuchtet, bevor sie verblasst. */
const HALTEDAUER_MS = 500;

/** Abnahme der Deckkraft je Bild waehrend des Verblassens. */
const VERBLASSEN_PRO_BILD = 0.02;

export function markenRaster(canvas) {
    if (! canvas || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const kontext = canvas.getContext('2d');

    if (! kontext) {
        return;
    }

    const flaeche = canvas.parentElement;

    // Die Leuchtfarbe kommt aus dem Marken-Token, damit sie sich mit dem
    // Farbsystem aendert und nicht doppelt gepflegt werden muss.
    const leuchtfarbe = rgbKanaele(
        getComputedStyle(canvas).getPropertyValue('--brand-accent') || '#4ADE9B'
    );

    let breite = 0;
    let hoehe = 0;
    let spalten = 0;
    let laeuft = false;

    /** @type {Map<number, {deckkraft: number, beruehrt: number}>} */
    const zellen = new Map();

    function vermessen() {
        const masse = canvas.getBoundingClientRect();

        // Auf zwei begrenzt: darueber kostet die Pixeldichte mehr, als sie am
        // Ergebnis sichtbar macht.
        const dichte = Math.min(window.devicePixelRatio || 1, 2);

        breite = Math.max(1, Math.round(masse.width));
        hoehe = Math.max(1, Math.round(masse.height));

        canvas.width = Math.round(breite * dichte);
        canvas.height = Math.round(hoehe * dichte);
        kontext.setTransform(dichte, 0, 0, dichte, 0, 0);

        spalten = Math.max(1, Math.ceil(breite / ZELLE));
        zellen.clear();
    }

    function zeichnen() {
        kontext.clearRect(0, 0, breite, hoehe);

        const jetzt = performance.now();

        for (const [nummer, zelle] of zellen) {
            if (jetzt - zelle.beruehrt > HALTEDAUER_MS) {
                zelle.deckkraft -= VERBLASSEN_PRO_BILD;
            }

            if (zelle.deckkraft <= 0) {
                zellen.delete(nummer);

                continue;
            }

            const links = (nummer % spalten) * ZELLE;
            const oben = Math.floor(nummer / spalten) * ZELLE;
            const mitteX = links + ZELLE / 2;
            const mitteY = oben + ZELLE / 2;

            // Radial vom Zellmittelpunkt nach aussen: der Rahmen ist innen
            // kraeftig und laeuft zu den Ecken hin aus, statt hart zu enden.
            const verlauf = kontext.createRadialGradient(mitteX, mitteY, 5, mitteX, mitteY, ZELLE);
            verlauf.addColorStop(0, `rgba(${leuchtfarbe}, ${zelle.deckkraft.toFixed(3)})`);
            verlauf.addColorStop(1, `rgba(${leuchtfarbe}, 0)`);

            kontext.strokeStyle = verlauf;
            kontext.lineWidth = 1.3;
            kontext.strokeRect(links + 0.5, oben + 0.5, ZELLE - 1, ZELLE - 1);
        }

        if (zellen.size === 0) {
            laeuft = false;

            return;
        }

        requestAnimationFrame(zeichnen);
    }

    function anstossen() {
        if (laeuft) {
            return;
        }

        laeuft = true;
        requestAnimationFrame(zeichnen);
    }

    flaeche.addEventListener('mousemove', (ereignis) => {
        const masse = canvas.getBoundingClientRect();
        const x = ereignis.clientX - masse.left;
        const y = ereignis.clientY - masse.top;

        if (x < 0 || y < 0 || x > masse.width || y > masse.height) {
            return;
        }

        const nummer = Math.floor(y / ZELLE) * spalten + Math.floor(x / ZELLE);
        const bekannt = zellen.get(nummer);

        if (bekannt) {
            bekannt.beruehrt = performance.now();
            bekannt.deckkraft = 1;

            return;
        }

        zellen.set(nummer, { deckkraft: 1, beruehrt: performance.now() });
        anstossen();
    });

    window.addEventListener('resize', vermessen);

    if (window.ResizeObserver) {
        new ResizeObserver(vermessen).observe(flaeche);
    }

    vermessen();
}

/**
 * Zerlegt eine Farbe in die drei Kanaele, wie rgba() sie erwartet.
 *
 * Akzeptiert `#RRGGBB` und `rgb(r, g, b)`, weil ein CSS-Token je nach Browser
 * in der einen oder anderen Form zurueckkommt.
 */
function rgbKanaele(farbe) {
    const wert = farbe.trim();

    if (wert.startsWith('#')) {
        const hex = wert.slice(1);
        const voll = hex.length === 3 ? [...hex].map((z) => z + z).join('') : hex;

        return [0, 2, 4].map((stelle) => parseInt(voll.slice(stelle, stelle + 2), 16)).join(', ');
    }

    const zahlen = wert.match(/\d+/g);

    return zahlen ? zahlen.slice(0, 3).join(', ') : '74, 222, 155';
}
