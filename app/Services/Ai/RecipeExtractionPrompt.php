<?php

namespace App\Services\Ai;

use App\Enums\Course;
use App\Enums\TagType;
use App\Models\Tag;
use App\Models\Unit;

/**
 * Prompt and response schema for turning a video or web page into one
 * recipe per dish it shows.
 */
final readonly class RecipeExtractionPrompt
{
    /**
     * @param  string  $source  What the model is given, e.g. "the attached video and its caption".
     * @param  string  $context  Caption or page content accompanying the source.
     * @param  ?int  $dishCount  Number of dishes the person sharing the source expects.
     */
    public function __construct(
        private string $source,
        private string $context,
        private ?int $dishCount = null,
    ) {}

    public function text(): string
    {
        $language = config('app.content_locale');

        return <<<PROMPT
        Extract the recipes from {$this->source}. Use everything available together: what is shown, what is said, on-screen text and the caption. Many videos only show the recipe or have no speech.

        Write all texts in the language with the code "{$language}". Return an empty list if there is no recipe.

        Dishes:
        - A source can show several separate dishes, for example three dinner ideas in one video. Return each dish as a recipe of its own, in the order they appear.
        - Every recipe only contains the ingredients, steps, tools and cover moments of its own dish. Never mix the dishes, and repeat an ingredient in each recipe that uses it.
        - Components of one dish such as a sauce, a topping or a side served with it are sections of that recipe and not recipes of their own.
        - {$this->dishes()}

        Title and description:
        - Use the name of the dish as title. Leave out series titles, episode numbers, slogans, hashtags, mentions and calls to follow.
        - Keep established dish names such as "Pulled Pork", "Smash Burger" or "Lemon Posset" as they are instead of translating them.
        - Describe the dish in one or two factual sentences.

        Servings and times:
        - servings is the number of portions as a number. A yield in pieces such as "12 cinnamon rolls" belongs in yield_text.
        - prep_minutes and cook_minutes only if they can be derived.

        Ingredients:
        - name is the food in its base form and singular, without amounts or preparation.
        - Keep the exact variety that is used, because varieties are different products: "Penne" or "Spaghetti" instead of "pasta", "Basmatireis" instead of "rice", "Rinderhackfleisch" instead of "minced meat". Only use the general name when the recipe leaves the variety open.
        - Preparation and purpose go into note, e.g. "diced" or "for caramelising".
        - unit is one of the allowed units or null for foods counted in pieces.
        - If an amount is only visible, estimate it realistically and set is_estimated to true.
        - An instruction such as "separate 4 eggs" is not an ingredient line of its own.
        - If a food serves two purposes, list it twice.
        - Give sauces, dressings and other components their own section name, otherwise leave section null.

        Steps:
        - Write well-formed steps in the imperative like a cookbook, including amounts, times, temperatures and signs of doneness.
        - Do not quote the original wording and do not split into tiny steps.
        - Put the number of every amount of food or liquid mentioned in a step, water included, in curly braces as a plain number, so it can be scaled with the servings: "{250} g Mehl", "{2} Eier", "{0.5} TL Salz", "{2}-{3} EL Öl". Never put times, temperatures, sizes or appliance levels in curly braces.
        - Group the steps of separate components such as base, filling and decoration by giving all steps of a component the same section name. Leave section null when the dish has no separate components.

        Tools:
        - List kitchen tools in singular, spelled as in a dictionary of the output language with its capitalization and special characters, for example "Küchenmaschine" in German. Always include the cooking appliance such as oven, pan, pot, deep fryer, airfryer, grill, microwave or kitchen torch, and for oven dishes the dish or tray.

        Classification:
        - course: the one main category of the dish, such as starter, main or dessert.
        - meals: one to three meals the dish suits.
        - tags: only values that clearly apply. "quick" means less than 30 minutes in total.

        Cover image:
        - cover_seconds: two to four moments of the video, in seconds, that show the finished dish of this recipe as appetisingly as possible. Leave empty if there is no video.

        Accompanying text:
        {$this->context}
        PROMPT;
    }

    /**
     * What is known about the number of dishes.
     */
    private function dishes(): string
    {
        return match ($this->dishCount) {
            null => 'Most sources show exactly one dish.',
            1 => 'The person who shared the source says it shows exactly one dish. Return a single recipe.',
            default => "The person who shared the source says it shows exactly {$this->dishCount} dishes. Return that many recipes.",
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $string = ['type' => 'STRING'];
        $optionalString = ['type' => 'STRING', 'nullable' => true];
        $optionalInteger = ['type' => 'INTEGER', 'nullable' => true];

        $properties = [
            'title' => $string,
            'description' => $string,
            'servings' => $optionalInteger,
            'yield_text' => $optionalString,
            'prep_minutes' => $optionalInteger,
            'cook_minutes' => $optionalInteger,
            'ingredients' => $this->listOf([
                'section' => $optionalString,
                'name' => $string,
                'quantity' => ['type' => 'NUMBER', 'nullable' => true],
                'unit' => ['type' => 'STRING', 'nullable' => true, 'enum' => Unit::whereNotNull('key')->orderBy('id')->pluck('key')->all()],
                'note' => $optionalString,
                'is_estimated' => ['type' => 'BOOLEAN'],
            ]),
            'steps' => $this->listOf(['section' => $optionalString, 'text' => $string]),
            'tools' => ['type' => 'ARRAY', 'items' => $string],
            'meals' => $this->keysOf(TagType::Meal),
            'course' => ['type' => 'STRING', 'enum' => array_column(Course::cases(), 'value')],
            'tags' => $this->keysOf(TagType::Tag),
            'cover_seconds' => ['type' => 'ARRAY', 'items' => ['type' => 'NUMBER']],
        ];

        return [
            'type' => 'OBJECT',
            'properties' => ['recipes' => $this->listOf($properties)],
            'required' => ['recipes'],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, mixed>
     */
    private function listOf(array $properties): array
    {
        return [
            'type' => 'ARRAY',
            'items' => ['type' => 'OBJECT', 'properties' => $properties, 'required' => array_keys($properties)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function keysOf(TagType $type): array
    {
        return [
            'type' => 'ARRAY',
            'items' => ['type' => 'STRING', 'enum' => Tag::where('type', $type)->orderBy('position')->pluck('key')->all()],
        ];
    }
}
