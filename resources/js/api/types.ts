export type BaseUnit = 'g' | 'ml' | 'piece';
export type ReviewStatus = 'suggested' | 'confirmed';
export type ValueSource = 'ai' | 'user' | 'import';
export type TagType = 'meal' | 'category' | 'tag';
export type MealType = 'breakfast' | 'lunch' | 'dinner' | 'dessert' | 'snack';
export type ImportStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface ServerInfo {
    name: string;
    content_locale: string;
    supported_locales: string[];
    auth_methods: ('password' | 'oidc')[];
    oidc: { issuer: string; client_id: string } | null;
}

export interface User {
    id: number;
    username: string;
    name: string;
    email: string | null;
}

export interface Session {
    token: string;
    user: User;
}

export interface Unit {
    id: number;
    key: string | null;
    code: string;
    name: string;
    plural_name: string;
    kind: 'mass' | 'volume' | 'product_specific';
    factor_to_base: number | null;
}

export interface StoreSection {
    id: number;
    key: string | null;
    name: string;
    position: number;
}

export interface Tag {
    id: number;
    type: TagType;
    key: string;
    name: string;
}

export interface Conversion {
    id: number;
    unit: Unit;
    amount_in_base: number;
    status: ReviewStatus;
    source: ValueSource;
}

export interface ProductPackage {
    id: number;
    label: string;
    amount_in_base: number;
    status: ReviewStatus;
    source: ValueSource;
}

export interface Product {
    id: number;
    name: string;
    plural_name: string | null;
    base_unit: BaseUnit;
    is_pantry_staple: boolean;
    shelf_life_opened_days: number | null;
    status: ReviewStatus;
    enriched_at: string | null;
    recipe_count?: number;
    store_section?: StoreSection | null;
    aliases?: string[];
    conversions?: Conversion[];
    packages?: ProductPackage[];
}

export interface ProductInput {
    name?: string;
    plural_name?: string | null;
    store_section_id?: number | null;
    base_unit?: BaseUnit;
    is_pantry_staple?: boolean;
    shelf_life_opened_days?: number | null;
}

export interface RecipeSummary {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    servings: number | null;
    yield_text: string | null;
    prep_minutes: number | null;
    cook_minutes: number | null;
    cover_url: string | null;
    tags?: Tag[];
}

export interface Ingredient {
    id: number;
    position: number;
    section: string | null;
    quantity: number | null;
    unit: Unit | null;
    product: Product | null;
    note: string | null;
    is_estimated: boolean;
    raw_text: string | null;
}

export interface Step {
    id: number;
    position: number;
    title: string | null;
    text: string;
}

export interface Recipe extends RecipeSummary {
    source_url: string | null;
    source_platform: string | null;
    meal_tags_locked: boolean;
    ingredients: Ingredient[];
    steps: Step[];
    tools: { id: number; name: string }[];
}

export interface IngredientInput {
    section: string | null;
    product_id: number | null;
    quantity: number | null;
    unit_id: number | null;
    note: string | null;
    is_estimated: boolean;
    raw_text: string | null;
}

export interface RecipeInput {
    title: string;
    description: string | null;
    servings: number | null;
    yield_text: string | null;
    prep_minutes: number | null;
    cook_minutes: number | null;
    source_url: string | null;
    tag_ids: number[];
    tools: string[];
    ingredients: IngredientInput[];
    steps: { title: string | null; text: string }[];
}

export interface RecipeImport {
    id: number;
    url: string;
    status: ImportStatus;
    error: string | null;
    error_message: string | null;
    created_at: string;
    recipe?: RecipeSummary | null;
}

export interface MealPlanEntry {
    id: number;
    date: string;
    meal: MealType;
    servings: number;
    cooked_at: string | null;
    recipe: RecipeSummary;
}

export interface PantryItem {
    id: number;
    amount: number;
    opened_on: string | null;
    expires_on: string | null;
    product: Product;
}

export interface ShoppingListItem {
    id: number;
    label: string | null;
    is_manual: boolean;
    needed_amount: number | null;
    purchase_amount: number | null;
    packages: { package_id: number; label: string; amount: number; count: number }[];
    leftover_amount: number;
    wasted_amount: number;
    uses: { date: string; recipe_id: number; title: string; amount: number }[];
    is_approximate: boolean;
    checked_at: string | null;
    product?: Product | null;
}

export interface ShoppingList {
    id: number;
    starts_on: string;
    ends_on: string;
    leftovers_stocked_at: string | null;
    items: ShoppingListItem[];
}

export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; total: number };
}
