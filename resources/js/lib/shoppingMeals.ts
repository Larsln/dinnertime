import type { BaseUnit, MealPlanEntry, ShoppingAmounts, ShoppingList } from '@/api/types';

/**
 * An ingredient a planned meal takes from the shopping list or from the
 * stock. `open` is still to buy, `done` bought, `skipped` left out and
 * `stocked` at home already.
 */
export interface MealIngredient {
    name: string;
    amount: number | null;
    baseUnit: BaseUnit;
    state: 'open' | 'done' | 'skipped' | 'stocked';
}

const STATES: MealIngredient['state'][] = ['open', 'done', 'skipped', 'stocked'];

type Use = { date: string; recipe_id: number; amount: number | null };

function amountFor(uses: Use[], entry: MealPlanEntry): number | null | undefined {
    return uses.find((use) => use.recipe_id === entry.recipe.id && use.date === entry.date)?.amount;
}

/**
 * What a list holds for one meal: the positions and their variants that
 * name the meal among their uses, followed by what is in stock. A product
 * with several positions, one bought and one for what is missing beyond
 * it, appears once and stays open until all of them are settled.
 */
export function ingredientsOf(list: ShoppingList, entry: MealPlanEntry): MealIngredient[] {
    const ingredients: MealIngredient[] = [];

    function add(name: string, baseUnit: BaseUnit, uses: Use[], state: MealIngredient['state']): void {
        const amount = amountFor(uses, entry);

        const known = ingredients.find((ingredient) => ingredient.name === name);

        if (amount === undefined) {
            return;
        }

        if (known === undefined) {
            ingredients.push({ name, amount, baseUnit, state });
        } else if (STATES.indexOf(state) < STATES.indexOf(known.state)) {
            known.state = state;
        }
    }

    for (const item of list.items) {
        const state = item.skipped_at !== null ? 'skipped' : item.checked_at !== null ? 'done' : 'open';
        const parts: (ShoppingAmounts & { name: string; base_unit: BaseUnit })[] = [
            {
                ...item,
                name: item.product?.name ?? item.label ?? '',
                base_unit: item.product?.base_unit ?? 'g',
            },
            ...item.variants,
        ];

        parts.forEach((part) => add(part.name, part.base_unit, part.uses, state));
    }

    list.in_stock.forEach((need) => add(need.name, need.base_unit, need.uses, 'stocked'));

    return ingredients;
}
