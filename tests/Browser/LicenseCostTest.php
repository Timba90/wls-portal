<?php

use App\Models\Product;
use App\Models\User;

it('laedt vorkonfigurierte gemeinsame USD-Kosten und speichert den EUR-Kalkulationsbetrag im Browser', function (): void {
    $this->actingAs(User::factory()->create());

    $product = Product::factory()->create([
        'name' => 'Gemeinsame Website-Lizenz',
        'purchase_cost_model' => 'shared_unlimited',
        'purchase_usage_unit' => 'website',
        'purchase_amount_cents' => 29999,
        'purchase_currency' => 'USD',
        'purchase_eur_amount_cents' => 29999,
        'purchase_interval_unit' => 'year',
        'purchase_interval_count' => 1,
        'default_purchase_price_cents' => 1234,
    ]);

    visit("/artikel/{$product->id}/bearbeiten")
        ->assertSee('Gemeinsame unbegrenzte Lizenz')
        ->assertSee('Webseite')
        ->assertSee('Originalwährung')
        ->assertSee('USD')
        ->assertDontSee('Standard-Einkaufspreis')
        ->assertValue('[wire\\:model="purchase_amount"]', '299,99')
        ->assertValue('[wire\\:model="purchase_eur_amount"]', '299,99')
        ->assertNoJavaScriptErrors()
        ->fill('[wire\\:model="purchase_eur_amount"]', '280,00')
        ->click('Speichern')
        ->waitForText('Artikel gespeichert.')
        ->assertSee('299,99 USD')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->purchase_eur_amount_cents)->toBe(28000)
        ->and($product->fresh()->default_purchase_price_cents)->toBe(1234)
        ->and($product->fresh()->purchase_currency)->toBe('USD');
});
