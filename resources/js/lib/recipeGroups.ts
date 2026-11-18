import type { Product } from '@/api/types';

/**
 * Ingredient line while it is edited.
 */
export interface IngredientRow {
    key: number;
    product: Product | null;
    quantity: number | null;
    unitId: number | null;
    note: string;
    isEstimated: boolean;
    rawText: string;
}

/**
 * Preparation step while it is edited.
 */
export interface StepRow {
    key: number;
    text: string;
}

/**
 * Named group of ingredient lines or steps, such as "Base" or "Filling".
 * A group without a title holds the lines that belong to no component.
 */
export interface EditGroup<Row> {
    key: number;
    title: string;
    rows: Row[];
}

let nextKey = 1;

/**
 * Key that stays unique among all rows and groups of the editor.
 */
export function newKey(): number {
    return nextKey++;
}

export function emptyIngredientRow(): IngredientRow {
    return {
        key: newKey(),
        product: null,
        quantity: null,
        unitId: null,
        note: '',
        isEstimated: false,
        rawText: '',
    };
}

export function emptyStepRow(): StepRow {
    return { key: newKey(), text: '' };
}

export function emptyGroup<Row>(row: Row): EditGroup<Row> {
    return { key: newKey(), title: '', rows: [row] };
}

/**
 * Turn stored lines into groups: consecutive lines with the same section
 * form one group.
 */
export function groupBySection<Item extends { section: string | null }, Row>(
    items: Item[],
    toRow: (item: Item) => Row,
): EditGroup<Row>[] {
    const groups: EditGroup<Row>[] = [];

    for (const item of items) {
        const last = groups.at(-1);

        if (last && last.title === (item.section ?? '')) {
            last.rows.push(toRow(item));
        } else {
            groups.push({ key: newKey(), title: item.section ?? '', rows: [toRow(item)] });
        }
    }

    return groups;
}

/**
 * Turn groups back into lines that each carry the title of their group.
 */
export function flattenGroups<Row, Line>(
    groups: EditGroup<Row>[],
    toLine: (row: Row, section: string | null) => Line | null,
): Line[] {
    return groups.flatMap((group) =>
        group.rows
            .map((row) => toLine(row, group.title.trim() || null))
            .filter((line): line is Line => line !== null),
    );
}
