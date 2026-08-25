<?php

use App\Actions\Documents\UploadDocument;
use App\Livewire\Shared\DocumentsPanel;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(config('portal.documents.disk'));
});

it('legt ein Dokument mit erster Version an', function (): void {
    $customer = Customer::factory()->create();
    $user = User::factory()->create();

    $document = app(UploadDocument::class)(
        documentable: $customer,
        file: UploadedFile::fake()->create('vertrag.pdf', 120, 'application/pdf'),
        user: $user,
    );

    $version = $document->currentVersion;

    expect($document->name)->toBe('vertrag.pdf')
        ->and($document->versions)->toHaveCount(1)
        ->and($version->version)->toBe(1)
        ->and($version->original_filename)->toBe('vertrag.pdf')
        ->and($version->mime_type)->toBe('application/pdf')
        ->and($version->uploaded_by)->toBe($user->id)
        ->and($version->checksum)->not->toBeNull();

    Storage::disk(config('portal.documents.disk'))->assertExists($version->path);
});

it('ersetzt eine alte Version nicht, sondern legt eine neue an', function (): void {
    $customer = Customer::factory()->create();

    $document = app(UploadDocument::class)($customer, UploadedFile::fake()->create('vertrag.pdf', 100, 'application/pdf'));
    $ersterPfad = $document->currentVersion->path;

    app(UploadDocument::class)(
        documentable: $customer,
        file: UploadedFile::fake()->create('vertrag-v2.pdf', 150, 'application/pdf'),
        document: $document,
    );

    $document->refresh();

    expect($document->versions)->toHaveCount(2)
        // Die hoechste Version ist automatisch die aktuelle.
        ->and($document->currentVersion->version)->toBe(2)
        ->and($document->currentVersion->original_filename)->toBe('vertrag-v2.pdf');

    // Die alte Datei bleibt physisch erhalten.
    Storage::disk(config('portal.documents.disk'))->assertExists($ersterPfad);
});

it('lehnt Dateien ueber der Groessengrenze ab', function (): void {
    config()->set('portal.documents.max_size_mb', 1);

    expect(fn () => app(UploadDocument::class)(
        Customer::factory()->create(),
        UploadedFile::fake()->create('gross.pdf', 2048, 'application/pdf'),
    ))->toThrow(ValidationException::class);
});

it('sperrt gefaehrliche Dateiendungen ueber die Blockliste', function (string $dateiname): void {
    expect(fn () => app(UploadDocument::class)(
        Customer::factory()->create(),
        UploadedFile::fake()->create($dateiname, 10),
    ))->toThrow(ValidationException::class);
})->with([
    'Windows-Programm' => 'schadcode.exe',
    'Batch-Datei' => 'start.bat',
    'PowerShell' => 'skript.ps1',
    'PHP' => 'shell.php',
]);

it('erlaubt gaengige Dokumenttypen', function (string $dateiname): void {
    $document = app(UploadDocument::class)(
        Customer::factory()->create(),
        UploadedFile::fake()->create($dateiname, 10),
    );

    expect($document->currentVersion)->not->toBeNull();
})->with([
    'PDF' => 'vertrag.pdf',
    'Word' => 'protokoll.docx',
    'Excel' => 'kalkulation.xlsx',
    'Bild' => 'screenshot.png',
    'Archiv' => 'unterlagen.zip',
]);

it('erkennt vorschaufaehige Formate', function (): void {
    $customer = Customer::factory()->create();

    $pdf = app(UploadDocument::class)($customer, UploadedFile::fake()->create('vertrag.pdf', 10, 'application/pdf'));
    $bild = app(UploadDocument::class)($customer, UploadedFile::fake()->image('foto.jpg'));
    $archiv = app(UploadDocument::class)($customer, UploadedFile::fake()->create('daten.zip', 10, 'application/zip'));

    expect($pdf->currentVersion->isPreviewable())->toBeTrue()
        ->and($bild->currentVersion->isPreviewable())->toBeTrue()
        ->and($bild->currentVersion->isImage())->toBeTrue()
        ->and($archiv->currentVersion->isPreviewable())->toBeFalse();
});

it('gibt Dateien nur angemeldeten Benutzern aus', function (): void {
    $document = app(UploadDocument::class)(
        Customer::factory()->create(),
        UploadedFile::fake()->create('vertrag.pdf', 10, 'application/pdf'),
    );

    $version = $document->currentVersion;

    $this->get(route('documents.download', [$document, $version]))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('documents.download', [$document, $version]))
        ->assertOk()
        ->assertDownload('vertrag.pdf');
});

it('gibt keine Version eines fremden Dokuments aus', function (): void {
    $customer = Customer::factory()->create();

    $ersterDokument = app(UploadDocument::class)($customer, UploadedFile::fake()->create('eins.pdf', 10, 'application/pdf'));
    $zweitesDokument = app(UploadDocument::class)($customer, UploadedFile::fake()->create('zwei.pdf', 10, 'application/pdf'));

    $this->actingAs(User::factory()->create())
        ->get(route('documents.download', [$ersterDokument, $zweitesDokument->currentVersion]))
        ->assertNotFound();
});

it('zeigt PDF und Bilder in der Vorschau an', function (): void {
    $document = app(UploadDocument::class)(
        Customer::factory()->create(),
        UploadedFile::fake()->create('vertrag.pdf', 10, 'application/pdf'),
    );

    $this->actingAs(User::factory()->create())
        ->get(route('documents.preview', [$document, $document->currentVersion]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('bietet nicht vorschaufaehige Dateien zum Download an', function (): void {
    $document = app(UploadDocument::class)(
        Customer::factory()->create(),
        UploadedFile::fake()->create('daten.zip', 10, 'application/zip'),
    );

    $this->actingAs(User::factory()->create())
        ->get(route('documents.preview', [$document, $document->currentVersion]))
        ->assertOk()
        ->assertDownload('daten.zip');
});

it('laedt ein Dokument ueber die Oberflaeche hoch', function (): void {
    $service = CustomerService::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(DocumentsPanel::class, ['documentable' => $service])
        ->call('create')
        ->set('file', UploadedFile::fake()->create('leistungsbeschreibung.pdf', 50, 'application/pdf'))
        ->set('description', 'Vereinbarter Leistungsumfang')
        ->call('upload')
        ->assertHasNoErrors();

    expect($service->documents()->count())->toBe(1)
        ->and($service->documents()->first()->description)->toBe('Vereinbarter Leistungsumfang');
});

it('archiviert ein Dokument und hebt die Archivierung auf', function (): void {
    $customer = Customer::factory()->create();

    $document = app(UploadDocument::class)($customer, UploadedFile::fake()->create('vertrag.pdf', 10, 'application/pdf'));

    $component = Livewire::actingAs(User::factory()->create())
        ->test(DocumentsPanel::class, ['documentable' => $customer]);

    $component->call('archive', $document->id);
    expect($document->fresh()->isArchived())->toBeTrue();

    $component->call('restore', $document->id);
    expect($document->fresh()->isArchived())->toBeFalse();
});

it('bietet SVG zum Download an statt es einzubetten', function (): void {
    $document = app(UploadDocument::class)(
        Customer::factory()->create(),
        UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    );

    // SVG zählt als Bild, kann aber Skripte tragen. Eingebettet liefe das im
    // Ursprung der Anwendung.
    $this->actingAs(User::factory()->create())
        ->get(route('documents.preview', [$document, $document->currentVersion]))
        ->assertOk()
        ->assertDownload('logo.svg');
});

it('setzt bei der Vorschau eine Inhaltsrichtlinie', function (): void {
    $document = app(UploadDocument::class)(
        Customer::factory()->create(),
        UploadedFile::fake()->create('vertrag.pdf', 10, 'application/pdf'),
    );

    $antwort = $this->actingAs(User::factory()->create())
        ->get(route('documents.preview', [$document, $document->currentVersion]))
        ->assertOk();

    // Ohne `default-src 'none'` dürfte eingebetteter Inhalt Skripte ausführen
    // und externe Ressourcen laden.
    expect($antwort->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
});
