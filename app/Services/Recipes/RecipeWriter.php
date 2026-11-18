<?php

namespace App\Services\Recipes;

use App\Models\Recipe;
use App\Services\Catalog\ConversionGaps;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates and updates recipes together with their ingredient lines, steps,
 * tags and tools.
 */
class RecipeWriter
{
    private const array NESTED = ['ingredients', 'steps', 'tag_ids', 'tool_ids'];

    public function __construct(private readonly ConversionGaps $gaps) {}

    /**
     * Create a recipe from validated data.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Recipe
    {
        return $this->save(new Recipe(['slug' => $this->uniqueSlug($data['title'])]), $data);
    }

    /**
     * Apply validated data to a recipe. Nested lists that are present
     * replace the existing ones, absent ones are left untouched.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Recipe $recipe, array $data): Recipe
    {
        return $this->save($recipe, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(Recipe $recipe, array $data): Recipe
    {
        DB::transaction(function () use ($recipe, $data) {
            $recipe->fill(Arr::except($data, self::NESTED))->save();

            if (array_key_exists('ingredients', $data)) {
                $recipe->ingredients()->delete();
                $recipe->ingredients()->createMany($this->numbered($data['ingredients']));
            }

            if (array_key_exists('steps', $data)) {
                $recipe->steps()->delete();
                $recipe->steps()->createMany($this->numbered($data['steps']));
            }

            if (array_key_exists('tag_ids', $data)) {
                $recipe->tags()->sync($data['tag_ids']);
            }

            if (array_key_exists('tool_ids', $data)) {
                $recipe->tools()->sync($data['tool_ids']);
            }
        });

        $this->gaps->enrichProductsOf($recipe);

        return $recipe;
    }

    /**
     * Add the display position to each row.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function numbered(array $rows): array
    {
        return array_map(
            fn (array $row, int $index) => [...$row, 'position' => $index + 1],
            $rows,
            array_keys($rows),
        );
    }

    /**
     * Derive a slug from the title, numbering it when it is taken.
     */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'recipe';
        $slug = $base;

        for ($suffix = 2; Recipe::where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
