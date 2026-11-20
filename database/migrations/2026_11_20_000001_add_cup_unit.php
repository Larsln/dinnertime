<?php

use App\Enums\UnitKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array LABELS = ['tasse', 'tassen', 'cup', 'cups'];

    /**
     * Run the migrations.
     *
     * Adds the cup as a built-in unit. A cup the household added before,
     * for example through the Mealie import, is merged into it so the unit
     * does not show up twice. A new installation gets the cup from the
     * seeder, in its place among the other built-in units.
     */
    public function up(): void
    {
        if (! DB::table('units')->whereNotNull('key')->exists()) {
            return;
        }

        $cup = DB::table('units')->where('key', 'cup')->value('id') ?? DB::table('units')->insertGetId([
            'key' => 'cup',
            'kind' => UnitKind::ProductSpecific->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->householdCups() as $own) {
            $this->merge($own, $cup);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}

    /**
     * Units without a key whose code or name says cup.
     *
     * @return list<object>
     */
    private function householdCups(): array
    {
        return DB::table('units')
            ->whereNull('key')
            ->get()
            ->filter(fn (object $unit) => array_intersect(
                array_map(fn (?string $label) => mb_strtolower(trim((string) $label)), [$unit->code, $unit->name, $unit->plural_name]),
                self::LABELS,
            ) !== [])
            ->values()
            ->all();
    }

    /**
     * Point ingredients and conversions of a household cup at the built-in
     * one and remove it. A product that already converts the built-in cup
     * keeps that conversion.
     */
    private function merge(object $own, int $cup): void
    {
        DB::table('recipe_ingredients')->where('unit_id', $own->id)->update(['unit_id' => $cup]);

        $converted = DB::table('product_conversions')->where('unit_id', $cup)->pluck('product_id');
        DB::table('product_conversions')->where('unit_id', $own->id)->whereNotIn('product_id', $converted)->update(['unit_id' => $cup]);
        DB::table('product_conversions')->where('unit_id', $own->id)->delete();

        DB::table('units')->where('id', $own->id)->delete();

        if ($own->mealie_unit_id !== null) {
            DB::table('units')->where('id', $cup)->whereNull('mealie_unit_id')->update(['mealie_unit_id' => $own->mealie_unit_id, 'updated_at' => now()]);
        }
    }
};
