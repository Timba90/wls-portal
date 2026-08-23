<?php

use App\Actions\Documents\UploadDocument;
use App\Actions\Maintenance\DeletePermanently;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('portal.documents.disk'));
});

it('entfernt die Dateien eines Dokuments aus dem Speicher', function (): void {
    $kunde = Customer::factory()->company()->create();

    $dokument = app(UploadDocument::class)(
        documentable: $kunde,
        file: UploadedFile::fake()->create('vertrag.pdf', 12, 'application/pdf'),
    );

    $version = $dokument->versions()->firstOrFail();

    Storage::disk($version->disk)->assertExists($version->path);

    app(DeletePermanently::class)($kunde);

    Storage::disk($version->disk)->assertMissing($version->path);
});

it('behält die Dateien, wenn die Transaktion zurückgerollt wird', function (): void {
    $kunde = Customer::factory()->company()->create();

    $dokument = app(UploadDocument::class)(
        documentable: $kunde,
        file: UploadedFile::fake()->create('vertrag.pdf', 12, 'application/pdf'),
    );

    $version = $dokument->versions()->firstOrFail();

    // Eine umschliessende Transaktion, die zurueckgerollt wird: die Zeilen
    // bleiben bestehen, also duerfen die Dateien nicht verschwinden.
    DB::beginTransaction();
    app(DeletePermanently::class)($kunde);
    DB::rollBack();

    expect(Customer::query()->whereKey($kunde->id)->exists())->toBeTrue()
        ->and(Document::query()->whereKey($dokument->id)->exists())->toBeTrue();

    Storage::disk($version->disk)->assertExists($version->path);
});

it('entfernt einen Kunden samt Leistungen in einem Zug', function (): void {
    $kunde = Customer::factory()->company()->create();
    CustomerService::factory()->count(3)->for($kunde)->create();

    $entfernt = app(DeletePermanently::class)($kunde);

    expect($entfernt['leistungen'])->toBe(3)
        ->and(CustomerService::query()->where('customer_id', $kunde->id)->exists())->toBeFalse();
});

it('entfernt auch archivierte Leistungen eines Kunden', function (): void {
    $kunde = Customer::factory()->company()->create();
    CustomerService::factory()->for($kunde)->archived()->create();

    app(DeletePermanently::class)($kunde);

    expect(CustomerService::query()->where('customer_id', $kunde->id)->exists())->toBeFalse();
});
