<?php

use App\Http\Controllers\DocumentController;
use App\Livewire\Archive\ArchivePage;
use App\Livewire\Catalog\CategoryList;
use App\Livewire\Catalog\ProductDetail;
use App\Livewire\Catalog\ProductForm;
use App\Livewire\Catalog\ProductList;
use App\Livewire\Catalog\TagList;
use App\Livewire\Contacts\ContactDetail;
use App\Livewire\Contacts\ContactForm;
use App\Livewire\Contacts\ContactList;
use App\Livewire\Contacts\ContactRoleList;
use App\Livewire\Customers\CustomerDetail;
use App\Livewire\Customers\CustomerForm;
use App\Livewire\Customers\CustomerList;
use App\Livewire\CustomFields\CustomFieldDefinitionList;
use App\Livewire\Dashboard\DashboardPage;
use App\Livewire\Profile\ProfilePage;
use App\Livewire\Profile\SecurityPage;
use App\Livewire\Projects\ProjectDetail;
use App\Livewire\Projects\ProjectForm;
use App\Livewire\Projects\ProjectList;
use App\Livewire\Projects\ProjectTypeList;
use App\Livewire\Services\CustomerServiceDetail;
use App\Livewire\Services\CustomerServiceForm;
use App\Livewire\Services\ServiceOverview;
use App\Livewire\Users\UserList;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function (): void {
    Route::livewire('/dashboard', DashboardPage::class)->name('dashboard');

    Route::livewire('/kunden', CustomerList::class)->name('customers.index');
    Route::livewire('/kunden/neu', CustomerForm::class)->name('customers.create');
    Route::livewire('/kunden/{customer}', CustomerDetail::class)->name('customers.show');
    Route::livewire('/kunden/{customer}/bearbeiten', CustomerForm::class)->name('customers.edit');

    Route::livewire('/kunden/{customer}/leistungen/neu', CustomerServiceForm::class)->name('customer-services.create');
    Route::livewire('/kunden/{customer}/leistungen/{service}', CustomerServiceDetail::class)->name('customer-services.show');
    Route::livewire('/kunden/{customer}/leistungen/{service}/bearbeiten', CustomerServiceForm::class)->name('customer-services.edit');

    Route::livewire('/ansprechpartner', ContactList::class)->name('contacts.index');
    Route::livewire('/ansprechpartner/neu', ContactForm::class)->name('contacts.create');
    Route::livewire('/ansprechpartner/rollen', ContactRoleList::class)->name('contact-roles.index');
    Route::livewire('/ansprechpartner/{contact}', ContactDetail::class)->name('contacts.show');
    Route::livewire('/ansprechpartner/{contact}/bearbeiten', ContactForm::class)->name('contacts.edit');

    Route::livewire('/artikel', ProductList::class)->name('products.index');
    Route::livewire('/artikel/neu', ProductForm::class)->name('products.create');
    Route::livewire('/artikel/kategorien', CategoryList::class)->name('categories.index');
    Route::livewire('/artikel/tags', TagList::class)->name('tags.index');
    Route::livewire('/artikel/{product}', ProductDetail::class)->name('products.show');
    Route::livewire('/artikel/{product}/bearbeiten', ProductForm::class)->name('products.edit');

    Route::livewire('/projekte', ProjectList::class)->name('projects.index');
    Route::livewire('/projekte/neu', ProjectForm::class)->name('projects.create');
    Route::livewire('/projekte/typen', ProjectTypeList::class)->name('project-types.index');
    Route::livewire('/projekte/{project}', ProjectDetail::class)->name('projects.show');
    Route::livewire('/projekte/{project}/bearbeiten', ProjectForm::class)->name('projects.edit');

    Route::livewire('/leistungen', ServiceOverview::class)->name('services.index');
    Route::livewire('/archiv', ArchivePage::class)->name('archive.index');

    Route::livewire('/felder', CustomFieldDefinitionList::class)->name('custom-fields.index');

    // Dokumente liegen in privatem Speicher; jeder Zugriff laeuft authentifiziert
    // ueber die Anwendung.
    Route::get('/dokumente/{document}/versionen/{version}/download', [DocumentController::class, 'download'])
        ->name('documents.download');
    Route::get('/dokumente/{document}/versionen/{version}/vorschau', [DocumentController::class, 'preview'])
        ->name('documents.preview');

    Route::livewire('/profil', ProfilePage::class)->name('profile.show');
    Route::livewire('/profil/sicherheit', SecurityPage::class)->name('profile.security');

    Route::livewire('/benutzer', UserList::class)->name('users.index');
});
