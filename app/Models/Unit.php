<?php

namespace App\Models;

use App\Enums\UnitKind;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Unit of measurement used on ingredient lines.
 *
 * Built-in units carry a key and are labelled by translation, units added
 * by the household carry their own code and names.
 *
 * @property UnitKind $kind
 * @property ?float $factor_to_base
 */
#[Fillable(['key', 'code', 'name', 'plural_name', 'kind', 'factor_to_base', 'mealie_unit_id'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * Key of the built-in unit that counts whole items.
     */
    public const string PIECE_KEY = 'piece';

    /**
     * Abbreviation in the current locale.
     */
    public function displayCode(): string
    {
        return $this->code ?? __("reference.units.{$this->key}.code");
    }

    /**
     * Singular name in the current locale.
     */
    public function displayName(): string
    {
        return $this->name ?? __("reference.units.{$this->key}.name");
    }

    /**
     * Plural name in the current locale, falling back to the singular.
     */
    public function displayPluralName(): string
    {
        return $this->key !== null
            ? __("reference.units.{$this->key}.plural")
            : $this->plural_name ?? $this->displayName();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => UnitKind::class,
            'factor_to_base' => 'float',
        ];
    }
}
