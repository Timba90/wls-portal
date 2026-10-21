<?php

use App\Livewire\Catalog\ProductList;
use App\Livewire\Contacts\ContactList;
use App\Livewire\Customers\CustomerList;
use App\Livewire\Projects\ProjectList;
use App\Livewire\Registrar\CertificateList;
use App\Livewire\Registrar\DomainList;
use App\Livewire\Services\ServiceOverview;
use App\Models\Certificate;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Domain;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

it('accepts cleared select filters and restores list defaults without losing rows', function (string $component, string $model, string $rows, array $filters, array $defaults): void {
    $model::factory()->create();
    $test = Livewire::actingAs(User::factory()->create())->test($component);

    foreach ($filters as $filter) {
        $test->set($filter, null)
            ->assertViewHas($rows, fn ($results) => $results->total() === 1);
    }

    $test->set('search', 'keine-treffer')
        ->call('setPage', 3)
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('paginators.page', 1)
        ->assertViewHas($rows, fn ($results) => $results->total() === 1);

    foreach ($defaults as $key => $value) {
        $test->assertSet($key, $value);
    }

    $test->call('resetFilters')->assertViewHas($rows, fn ($results) => $results->total() === 1);
})->with([
    'customers' => [CustomerList::class, Customer::class, 'customers', ['status', 'type', 'responsibleUserId'], ['status' => 'active', 'type' => '', 'responsibleUserId' => '']],
    'projects' => [ProjectList::class, Project::class, 'projects', ['projectTypeId', 'responsibleUserId'], ['status' => 'open', 'projectTypeId' => '', 'responsibleUserId' => '']],
    'services' => [ServiceOverview::class, CustomerService::class, 'services', ['status', 'productId', 'categoryId', 'tagId', 'responsibleUserId', 'billingFilter', 'catalogFilter'], ['status' => 'active', 'productId' => '', 'categoryId' => '', 'tagId' => '', 'responsibleUserId' => '', 'billingFilter' => '', 'catalogFilter' => '']],
    'products' => [ProductList::class, Product::class, 'products', ['categoryId', 'tagId'], ['status' => 'active', 'categoryId' => '', 'tagId' => '']],
    'contacts' => [ContactList::class, Contact::class, 'contacts', ['status', 'roleId'], ['status' => 'active', 'roleId' => '']],
    'domains' => [DomainList::class, Domain::class, 'domains', ['provider', 'assignment', 'customerFilter', 'expiry'], ['provider' => '', 'assignment' => '', 'customerFilter' => '', 'expiry' => '']],
    'certificates' => [CertificateList::class, Certificate::class, 'certificates', ['provider', 'assignment', 'expiry'], ['provider' => '', 'assignment' => '', 'expiry' => '']],
]);
