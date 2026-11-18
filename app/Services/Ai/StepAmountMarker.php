<?php

namespace App\Services\Ai;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Support\StepAmounts;

/**
 * Lets Gemini mark the ingredient amounts in the steps of a recipe, so
 * they scale with the servings. Used for recipes that were written by hand
 * or imported without markers.
 */
class StepAmountMarker
{
    public function __construct(private readonly GeminiClient $gemini) {}

    /**
     * Mark the amounts and return how many steps changed. An answer that
     * alters the wording of a step is ignored for that step.
     *
     * @throws GeminiException
     */
    public function mark(Recipe $recipe): int
    {
        $steps = $recipe->steps()->orderBy('position')->get();

        if ($steps->isEmpty()) {
            return 0;
        }

        $answer = $this->gemini->generate(
            config('services.gemini.video_model'),
            [['text' => $this->prompt($recipe, $steps->pluck('text')->all())]],
            ['type' => 'OBJECT', 'properties' => ['steps' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']]], 'required' => ['steps']],
        );

        $changed = 0;

        foreach ($steps as $index => $step) {
            $marked = $answer['steps'][$index] ?? null;

            if (is_string($marked) && $marked !== $step->text && StepAmounts::marks($marked, $step->text)) {
                $step->update(['text' => $marked]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * @param  list<string>  $steps
     */
    private function prompt(Recipe $recipe, array $steps): string
    {
        $ingredients = $recipe->ingredients()->with('product', 'unit')->get()
            ->map(fn (RecipeIngredient $ingredient) => trim("{$ingredient->quantity} {$ingredient->unit?->code} {$ingredient->product->name}"))
            ->implode("\n");
        $numbered = json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
        The steps of a recipe follow as a JSON list. Return the same list with every amount of an ingredient marked by putting its number in curly braces, so the amounts can be scaled with the servings.

        Rules:
        - Only add curly braces around numbers. Do not change, add or remove any other character, and keep the number exactly as written: "250 g Mehl" becomes "{250} g Mehl", "1,5 EL Öl" becomes "{1,5} EL Öl", "1/2 TL Salz" becomes "{1/2} TL Salz", "2-3 Eier" becomes "{2}-{3} Eier".
        - Mark every amount of food or liquid, whether given with a unit or counted in pieces, also for water and anything else missing from the ingredient list.
        - Never mark times, temperatures, sizes such as centimetres, speeds or levels of appliances, step numbers, or the number of pieces the dough or dish is divided into.
        - Numbers written as words or with special fraction characters stay unmarked.
        - Return every step, in the same order, also those without amounts.

        Ingredients of the recipe:
        {$ingredients}

        Steps:
        {$numbered}
        PROMPT;
    }
}
