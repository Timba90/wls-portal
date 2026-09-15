<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ob die Signierschluessel in diesem Lauf schon geprueft wurden.
     */
    private static bool $schluesselGeprueft = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureOAuthKeys();
    }

    /**
     * Legt die Signierschluessel von Passport an, wenn sie fehlen.
     *
     * Sie gehoeren nicht ins Repository und stehen deshalb in `.gitignore` —
     * womit sie auf einem frisch geklonten Arbeitsplatz und in der CI fehlen.
     * Ohne sie scheitert jeder Test, der ein Token ausstellt oder auch nur die
     * Zustimmungsseite aufruft, mit „Invalid key supplied".
     *
     * Einmal je Lauf, nicht je Test: `passport:keys` kostet spuerbar Zeit.
     */
    private function ensureOAuthKeys(): void
    {
        if (self::$schluesselGeprueft) {
            return;
        }

        self::$schluesselGeprueft = true;

        if (! file_exists(storage_path('oauth-private.key'))) {
            Artisan::call('passport:keys', ['--force' => true]);
        }
    }
}
