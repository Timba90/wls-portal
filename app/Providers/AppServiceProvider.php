<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use App\Models\Project;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use TallStackUi\Facades\TallStackUi;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurePasswordRules();
        $this->configureModels();
        $this->configureUrls();
        $this->configureNavigationCounts();
        $this->configureFormFieldPaddings();
        $this->configureProgressTrack();

        Date::use(Carbon::class);
    }

    /**
     * Waagerechte Polsterung der Eingabefelder.
     *
     * TallStackUI gibt fuer das Basisfeld nur `py-1.5` aus und ueberlaesst die
     * waagerechte Polsterung dem Forms-Plugin. Das laedt das Paket allerdings
     * selbst mit `strategy: 'class'`, und seine Felder tragen kein
     * `form-input` — der Text klebte deshalb am linken Rand.
     *
     * Die Korrektur sitzt hier statt an 150 einzelnen Aufrufen. Felder mit
     * Symbol, Prefix oder Suffix bringen eigene Polsterungsklassen mit
     * (`pl-3 pr-0`, `pl-8`, `pr-8`); die stehen in Tailwinds Sortierung hinter
     * `px-3` und behalten deshalb die Oberhand.
     */
    private function configureFormFieldPaddings(): void
    {
        foreach (['input', 'textarea', 'number', 'tag', 'currency', 'password', 'date', 'time'] as $component) {
            TallStackUi::customize()
                ->form($component)
                ->block('input.base')
                ->append('px-3');
        }

        // Die Auswahlfelder haengen an einem eigenen Einstiegspunkt.
        foreach (['styled', 'native'] as $component) {
            TallStackUi::customize()
                ->select($component)
                ->block('input.base')
                ->append('px-3');
        }
    }

    /**
     * Fortschrittsleiste auf die Flaechentokens des Entwurfs bringen.
     *
     * TallStackUI faerbt die Spur fest mit `bg-gray-200 dark:bg-gray-700`. Das
     * ist im dauerhaften Dunkelmodus deutlich heller als jede andere Flaeche
     * der Anwendung; `bg-raised` ist derselbe Ton wie Tabellenkopf und
     * Kennzahlkacheln.
     */
    private function configureProgressTrack(): void
    {
        TallStackUi::customize()
            ->progress()
            ->block('simple.wrapper')
            ->replace('bg-gray-200 dark:bg-gray-700', 'bg-raised');
    }

    /**
     * Passwörter: mindestens 12 Zeichen mit Groß- und Kleinbuchstaben, Zahl und
     * Sonderzeichen. Kein automatischer Ablauf.
     */
    private function configurePasswordRules(): void
    {
        Password::defaults(fn (): Password => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols());
    }

    private function configureModels(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
        Model::automaticallyEagerLoadRelationships();
    }

    /**
     * Zaehler der Seitennavigation.
     *
     * Nur fuer das angemeldete Layout und nur einmal je Request — der
     * once()-Helfer verhindert, dass verschachtelte Livewire-Komponenten die
     * Abfragen wiederholen.
     */
    private function configureNavigationCounts(): void
    {
        View::composer('components.layouts.app', function (ViewContract $view): void {
            $view->with('navCounts', once(fn (): array => [
                'customers' => Customer::query()->active()->count(),
                'projects' => Project::query()->running()->count(),
                'services' => CustomerService::query()->active()->count(),
                'contacts' => Contact::query()->active()->count(),
                'products' => Product::query()->active()->count(),
            ]));
        });
    }

    private function configureUrls(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
