<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        Date::use(Carbon::class);
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
