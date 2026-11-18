<?php

namespace App\Services\Import;

use App\Enums\Course;
use App\Enums\TagType;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Unit;
use App\Services\Catalog\ProductResolver;
use App\Services\Recipes\RecipeWriter;
use App\Support\SourceLink;

/**
 * Stores a recipe the AI extracted, linking ingredients to products and
 * resolving unit and tag keys.
 */
class RecipeDraftWriter
{
    public function __construct(
        private readonly RecipeWriter $writer,
        private readonly ProductResolver $products,
    ) {}

    /**
     * @param  array<string, mixed>  $draft  Answer matching the recipe extraction schema.
     * @param  string  $url  Link the recipe was imported from.
     * @param  ?string  $videoId  Id of the video, if the platform reported one.
     */
    public function write(array $draft, string $url, ?string $videoId = null): Recipe
    {
        $source = SourceLink::fromUrl($url);

        return $this->writer->create([
            'title' => $draft['title'],
            'description' => $draft['description'] ?: null,
            'course' => Course::tryFrom($draft['course']),
            'servings' => $draft['servings'] > 0 ? $draft['servings'] : null,
            'yield_text' => $draft['yield_text'] ?: null,
            'prep_minutes' => $draft['prep_minutes'],
            'cook_minutes' => $draft['cook_minutes'],
            'source_url' => $url,
            'source_platform' => $source->platform,
            'source_video_id' => $videoId ?? $source->videoId,
            'ingredients' => $this->ingredientRows($draft['ingredients']),
            'steps' => array_map(fn (array $step) => ['title' => $step['title'] ?: null, 'text' => $step['text']], $draft['steps']),
            'tools' => array_values(array_unique($draft['tools'])),
            'tag_ids' => [
                ...$this->tagIds(TagType::Meal, $draft['meals']),
                ...$this->tagIds(TagType::Tag, $draft['tags']),
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $ingredients
     * @return list<array<string, mixed>>
     */
    private function ingredientRows(array $ingredients): array
    {
        $unitIds = Unit::whereNotNull('key')->pluck('id', 'key');
        $rows = [];

        foreach ($ingredients as $ingredient) {
            if (filled($ingredient['name'])) {
                $rows[] = [
                    'section' => $ingredient['section'] ?: null,
                    'product_id' => $this->products->resolve($ingredient['name'])->id,
                    'quantity' => $ingredient['quantity'] > 0 ? $ingredient['quantity'] : null,
                    'unit_id' => $unitIds[$ingredient['unit']] ?? null,
                    'note' => $ingredient['note'] ?: null,
                    'is_estimated' => $ingredient['is_estimated'],
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>  $keys
     * @return list<int>
     */
    private function tagIds(TagType $type, array $keys): array
    {
        return Tag::where('type', $type)->whereIn('key', $keys)->pluck('id')->all();
    }
}
