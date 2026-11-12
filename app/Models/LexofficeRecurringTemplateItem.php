<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\LexofficeRecurringTemplateItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Position einer Lexoffice-Serienrechnung.
 *
 * Die Zuordnung zur Kundenleistung (`customer_service_id`) und das Ignorieren
 * sind Portal-Daten und ueberstehen jeden Abgleich. `match_source` haelt fest,
 * ob die Zuordnung automatisch oder von Hand entstand.
 */
#[Fillable([
    'lexoffice_recurring_template_id',
    'lexoffice_line_item_id',
    'position',
    'type',
    'name',
    'description',
    'quantity',
    'unit_name',
    'unit_price_net_cents',
    'discount_percentage',
    'tax_rate_percentage',
    'line_net_cents',
    'customer_service_id',
    'match_source',
    'is_ignored',
])]
class LexofficeRecurringTemplateItem extends Model
{
    /** @use HasFactory<LexofficeRecurringTemplateItemFactory> */
    use HasFactory;

    public const string MatchedAutomatically = 'auto';

    public const string MatchedManually = 'manual';

    /**
     * @return BelongsTo<LexofficeRecurringTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(LexofficeRecurringTemplate::class, 'lexoffice_recurring_template_id');
    }

    /**
     * @return BelongsTo<CustomerService, $this>
     */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    /**
     * Netto-Betrag der Position je Ausfuehrung der Vorlage.
     */
    public function lineNet(): Money
    {
        return Money::fromCents($this->line_net_cents);
    }

    /**
     * Netto-Betrag auf einen Monat umgerechnet.
     */
    public function monthlyNet(): Money
    {
        return $this->template->execution_interval->billingInterval()->toMonthly($this->lineNet());
    }

    /**
     * Netto-Betrag auf ein Jahr umgerechnet.
     */
    public function yearlyNet(): Money
    {
        return $this->template->execution_interval->billingInterval()->toYearly($this->lineNet());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'float',
            'unit_price_net_cents' => 'integer',
            'discount_percentage' => 'float',
            'tax_rate_percentage' => 'float',
            'line_net_cents' => 'integer',
            'is_ignored' => 'boolean',
        ];
    }
}
