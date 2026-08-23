<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Product;

/**
 * Bereich, fuer den benutzerdefinierte Felder definiert werden.
 *
 * Bewusst als Enum statt als freier Model-Name: die Liste waechst
 * kontrolliert, wenn spaeter Projekte oder Domains hinzukommen.
 */
enum CustomFieldEntity: string
{
    use HasOptions;

    case Customer = 'customer';
    case Product = 'product';
    case CustomerService = 'customer_service';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Kunden',
            self::Product => 'Artikel / Leistungen',
            self::CustomerService => 'Kundenleistungen',
        };
    }

    /**
     * @return class-string
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Customer => Customer::class,
            self::Product => Product::class,
            self::CustomerService => CustomerService::class,
        };
    }

    public static function forModel(object $model): self
    {
        return match ($model::class) {
            Customer::class => self::Customer,
            Product::class => self::Product,
            CustomerService::class => self::CustomerService,
            default => throw new \InvalidArgumentException(
                'Für '.$model::class.' sind keine benutzerdefinierten Felder vorgesehen.'
            ),
        };
    }
}
