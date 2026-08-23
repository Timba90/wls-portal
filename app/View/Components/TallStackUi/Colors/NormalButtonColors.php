<?php

namespace App\View\Components\TallStackUi\Colors;

use Illuminate\View\Component;

/**
 * Farben der Schaltflächen aus dem Entwurf „WLS Portal".
 *
 * TallStackUI verwendet für die gefüllte Variante im Dark Mode einen dunkleren
 * Ton (`primary-700`) und helle Schrift. Der Entwurf setzt in beiden
 * Erscheinungsbildern dieselbe Minze mit dunkler Schrift. Nur `primary` und
 * `secondary` werden überschrieben; alle übrigen Farben bleiben `null` und
 * damit auf dem Standard des Pakets.
 *
 * Die Datei entspricht dem Stub von `tallstackui:setup-color` — das Kommando
 * selbst verlangt eine interaktive Auswahl und lässt sich hier nicht ausführen.
 */
class NormalButtonColors
{
    /**
     * @return array<string, array<string, ?string>>
     */
    public function backgroundColors(Component $component): array
    {
        return [
            'solid' => [
                'primary' => 'border-transparent bg-accent text-accent-ink '
                    .'hover:bg-accent-hover focus:bg-accent-hover '
                    .'focus:ring-accent focus:ring-offset-2 focus:ring-offset-canvas',
                'secondary' => 'border border-line-strong bg-raised text-ink-base '
                    .'hover:border-ink-faint hover:text-ink '
                    .'focus:ring-accent focus:ring-offset-2 focus:ring-offset-canvas',
            ],
            'outline' => [
                'primary' => 'border border-accent bg-transparent text-accent '
                    .'hover:bg-accent/10 focus:ring-accent focus:ring-offset-0',
                'secondary' => 'border border-line-strong bg-transparent text-ink-muted '
                    .'hover:border-ink-faint hover:text-ink focus:ring-accent focus:ring-offset-0',
            ],
            'light' => [
                'primary' => 'border-transparent bg-accent/15 text-accent hover:bg-accent/25',
            ],
            'flat' => [
                'primary' => 'border-transparent bg-transparent text-accent hover:bg-accent/10',
            ],
        ];
    }

    /**
     * @return array<string, array<string, ?string>>
     */
    public function iconColors(Component $component): array
    {
        return [
            'solid' => [
                'primary' => 'text-accent-ink',
                'secondary' => 'text-ink-base',
            ],
            'outline' => [
                'primary' => 'text-accent',
                'secondary' => 'text-ink-muted',
            ],
            'light' => [
                'primary' => 'text-accent',
            ],
            'flat' => [
                'primary' => 'text-accent',
            ],
        ];
    }
}
