<?php

namespace App\Services\Import;

use App\Enums\Course;
use App\Enums\SideKind;
use App\Enums\TagType;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Unit;
use App\Services\Catalog\ProductResolver;
use App\Services\Catalog\ToolResolver;
use App\Services\Recipes\RecipeWriter;
use App\Support\SourceLink;

/**
 * Stores a recipe the AI extracted, linking ingredients to products, tools
 * to the catalog and resolving unit and tag keys.
 */
class RecipeDraftWriter
{
    public function __construct(
        private readonly RecipeWriter $writer,
        private readonly ProductResolver $products,
        private readonly ToolResolver $tools,
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
            ...$this->sides($draft),
            'servings' => $draft['servings'] > 0 ? $draft['servings'] : null,
            'yield_text' => $draft['yield_text'] ?: null,
            'prep_minutes' => $draft['prep_minutes'],
            'cook_minutes' => $draft['cook_minutes'],
            'source_url' => $url,
            'source_platform' => $source->platform,
            'source_video_id' => $videoId ?? $source->videoId,
            'ingredients' => $this->ingredientRows($draft['ingredients']),
            'steps' => array_map(fn (array $step) => ['section' => $step['section'] ?: null, 'text' => $step['text']], $draft['steps']),
            'tool_ids' => $this->toolIds($draft['tools']),
            'tag_ids' => [
                ...$this->tagIds(TagType::Meal, $draft['meals']),
                ...$this->tagIds(TagType::Tag, $draft['tags']),
            ],
        ]);
    }

    /**
     * What kind of side the dish is, or which kinds it is served with. A
     * side is not served with sides itself.
     *
     * @param  array<string, mixed>  $draft
     * @return array{side_kind: ?SideKind, side_options: list<string>}
     */
    private function sides(array $draft): array
    {
        $kind = SideKind::tryFrom($draft['side_kind'] ?? '');
        $options = array_filter($draft['side_options'] ?? [], fn (mixed $option) => SideKind::tryFrom($option) !== null);

        return [
            'side_kind' => $kind,
            'side_options' => $kind === null ? array_values(array_unique($options)) : [],
        ];
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
     * @param  list<string>  $names
     * @return list<int>
     */
    private function toolIds(array $names): array
    {
        $ids = array_map(fn (string $name) => $this->tools->resolve($name)->id, array_filter($names));

        return array_values(array_unique($ids));
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
