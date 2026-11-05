<?php

use App\Http\Controllers\CustomerPortal\InvoiceDownloadController;
use App\Http\Controllers\CustomerPortal\LogoutController;
use App\Http\Middleware\EnsurePortalUserIsActive;
use App\Livewire\CustomerPortal\Auth\ForgotPassword;
use App\Livewire\CustomerPortal\Auth\Login;
use App\Livewire\CustomerPortal\Auth\ResetPassword;
use App\Livewire\CustomerPortal\DashboardPage;
use App\Livewire\CustomerPortal\InvoiceList;
use App\Livewire\CustomerPortal\ProfilePage;
use App\Livewire\CustomerPortal\ProjectDetail;
use App\Livewire\CustomerPortal\ProjectList;
use App\Livewire\CustomerPortal\SepaMandatePage;
use App\Livewire\CustomerPortal\TicketDetail;
use App\Livewire\CustomerPortal\TicketForm;
use App\Livewire\CustomerPortal\TicketList;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kundenportal
|--------------------------------------------------------------------------
|
| Eingebunden in bootstrap/app.php mit Praefix (oder eigener Domain) und dem
| Routennamen `customer-portal.`. Alle Seiten laufen ueber den Guard
| `customer`; interne Benutzer kommen hier nicht hinein und Kunden nicht in
| die interne Verwaltung.
|
*/

Route::middleware('guest:customer')->group(function (): void {
    Route::livewire('/anmelden', Login::class)->name('login');
    Route::livewire('/passwort-vergessen', ForgotPassword::class)->name('password.request');
    Route::livewire('/passwort-festlegen/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware(['auth:customer', EnsurePortalUserIsActive::class])->group(function (): void {
    Route::livewire('/', DashboardPage::class)->name('dashboard');

    Route::livewire('/rechnungen', InvoiceList::class)->name('invoices.index');
    Route::get('/rechnungen/{invoice}/pdf', InvoiceDownloadController::class)
        ->whereNumber('invoice')
        ->middleware('throttle:30,1')
        ->name('invoices.download');

    Route::livewire('/sepa-mandat', SepaMandatePage::class)->name('sepa-mandate');

    Route::livewire('/tickets', TicketList::class)->name('tickets.index');
    Route::livewire('/tickets/neu', TicketForm::class)->name('tickets.create');
    Route::livewire('/tickets/{ticket}', TicketDetail::class)->name('tickets.show');

    Route::livewire('/projekte', ProjectList::class)->name('projects.index');
    Route::livewire('/projekte/{project}', ProjectDetail::class)->name('projects.show');

    Route::livewire('/profil', ProfilePage::class)->name('profile');

    Route::post('/abmelden', LogoutController::class)->name('logout');
});
