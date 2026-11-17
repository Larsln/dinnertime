<?php

namespace App\Services\Mealie;

use App\Enums\TagType;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Tool;
use App\Models\Unit;
use App\Support\ReferenceTranslations;
use App\Support\SourceLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Imports recipes from Mealie. Requires the catalog to be imported first.
 */
class RecipeImporter
{
    private const string ESTIMATED_MARKER = '(Menge geschätzt)';

    /**
     * @var array<string, true> Mealie organizer names without a matching tag.
     */
    private array $unknownTags = [];

    public function __construct(private readonly MealieClient $mealie) {}

    /**
     * Import all recipes and report how many were written or left untouched.
     *
     * @return array{imported: int, skipped: int}
     */
    public function import(bool $overwrite): array
    {
        $result = ['imported' => 0, 'skipped' => 0];

        foreach ($this->mealie->recipeSummaries() as $summary) {
            if (! $overwrite && Recipe::where('mealie_recipe_id', $summary['id'])->exists()) {
                $result['skipped']++;

                continue;
            }

            $this->importRecipe($this->mealie->recipe($summary['slug']));
            $result['imported']++;
        }

        return $result;
    }

    /**
     * Names of Mealie tags and categories that are not part of the fixed lists.
     *
     * @return list<string>
     */
    public function unknownTags(): array
    {
        return array_keys($this->unknownTags);
    }

    /**
     * Write a single recipe with all of its parts.
     *
     * @param  array<string, mixed>  $data
     */
    private function importRecipe(array $data): void
    {
        $recipe = DB::transaction(function () use ($data) {
            $recipe = Recipe::updateOrCreate(
                ['mealie_recipe_id' => $data['id']],
                $this->recipeAttributes($data),
            );

            $recipe->ingredients()->delete();
            $recipe->ingredients()->createMany($this->ingredientRows($data['recipeIngredient']));

            $recipe->steps()->delete();
            $recipe->steps()->createMany($this->stepRows($data['recipeInstructions']));

            $recipe->tags()->sync($this->tagIds($data));
            $recipe->tools()->sync($this->toolIds($data['tools']));

            return $recipe;
        });

        $this->storeCover($recipe, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function recipeAttributes(array $data): array
    {
        $source = SourceLink::fromUrl($data['orgURL']);

        return [
            'title' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?: null,
            'servings' => $data['recipeServings'] > 0 ? (int) round($data['recipeServings']) : null,
            'yield_text' => $this->yieldText($data),
            'prep_minutes' => $this->minutes($data['prepTimeSeconds']),
            'cook_minutes' => $this->minutes($data['performTimeSeconds']),
            'source_url' => $data['orgURL'] ?: null,
            'source_platform' => $source->platform,
            'source_video_id' => $source->videoId,
        ];
    }

    /**
     * Combine yield quantity and label, e.g. "12 Zimtschnecken".
     *
     * @param  array<string, mixed>  $data
     */
    private function yieldText(array $data): ?string
    {
        $quantity = $data['recipeYieldQuantity'] > 0 ? (string) (float) $data['recipeYieldQuantity'] : '';

        return trim("{$quantity} {$data['recipeYield']}") ?: null;
    }

    private function minutes(?int $seconds): ?int
    {
        return $seconds ? (int) round($seconds / 60) : null;
    }

    /**
     * Build ingredient rows. Mealie stores a section title only on the first
     * line of a section, so it is carried forward to the following lines.
     *
     * @param  list<array<string, mixed>>  $ingredients
     * @return list<array<string, mixed>>
     */
    private function ingredientRows(array $ingredients): array
    {
        $productIds = Product::whereNotNull('mealie_food_id')->pluck('id', 'mealie_food_id');
        $unitIds = Unit::whereNotNull('mealie_unit_id')->pluck('id', 'mealie_unit_id');
        $section = null;
        $rows = [];

        foreach ($ingredients as $index => $ingredient) {
            $section = $ingredient['title'] ?: $section;
            $note = (string) $ingredient['note'];

            $rows[] = [
                'position' => $index + 1,
                'section' => $section,
                'product_id' => $productIds[$ingredient['food']['id'] ?? null] ?? null,
                'quantity' => $ingredient['quantity'] ?: null,
                'unit_id' => $unitIds[$ingredient['unit']['id'] ?? null] ?? null,
                'note' => trim(str_replace(self::ESTIMATED_MARKER, '', $note)) ?: null,
                'is_estimated' => str_contains($note, self::ESTIMATED_MARKER),
                'raw_text' => $ingredient['originalText'] ?: $ingredient['display'],
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return list<array<string, mixed>>
     */
    private function stepRows(array $steps): array
    {
        $rows = [];

        foreach ($steps as $index => $step) {
            $rows[] = [
                'position' => $index + 1,
                'title' => $step['title'] ?: null,
                'text' => $step['text'],
            ];
        }

        return $rows;
    }

    /**
     * Resolve Mealie categories and tags against the fixed lists.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function tagIds(array $data): array
    {
        $ids = [];

        foreach ($data['recipeCategory'] as $category) {
            $ids[] = $this->findTagId($category['name'], [TagType::Category]);
        }

        foreach ($data['tags'] as $tag) {
            $ids[] = $this->findTagId($tag['name'], [TagType::Meal, TagType::Tag]);
        }

        return array_values(array_filter($ids));
    }

    /**
     * Find a fixed tag by its name in any supported language.
     *
     * @param  list<TagType>  $types  Vocabularies to search, in order of preference.
     */
    private function findTagId(string $name, array $types): ?int
    {
        foreach ($types as $type) {
            $key = ReferenceTranslations::findKey("reference.tags.{$type->value}", $name);

            if ($key !== null) {
                return Tag::where('type', $type)->where('key', $key)->value('id');
            }
        }

        $this->unknownTags[$name] = true;

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $tools
     * @return list<int>
     */
    private function toolIds(array $tools): array
    {
        return array_map(
            fn (array $tool) => Tool::firstOrCreate(['name' => $tool['name']])->id,
            $tools,
        );
    }

    /**
     * Download the cover image to the public disk.
     *
     * @param  array<string, mixed>  $data
     */
    private function storeCover(Recipe $recipe, array $data): void
    {
        $image = $data['image'] ? $this->mealie->recipeImage($data['id']) : null;

        if ($image === null) {
            return;
        }

        $path = "covers/{$recipe->id}.webp";
        Storage::disk('public')->put($path, $image);
        $recipe->update(['cover_image_path' => $path]);
    }
}
