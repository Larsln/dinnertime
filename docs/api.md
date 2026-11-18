# API

REST API with JSON bodies under `/api/v1`. Dates are `YYYY-MM-DD`, timestamps
ISO 8601 in UTC. Amounts of a product are always given in its base unit
(`g`, `ml` or `piece`).

## Conventions

- **Authentication:** `Authorization: Bearer <token>` on every endpoint except
  `GET /server`, `POST /auth/login` and `POST /auth/oidc`. Missing or invalid
  tokens are answered with `401`.
- **Language:** built-in names (units, store sections, tags) and messages are
  returned in the first supported language of the `Accept-Language` header.
- **Envelope:** resources are wrapped in `{"data": ...}`. Paginated lists add
  `links` and `meta` (`current_page`, `last_page`, `total`) and accept `?page=`.
- **Errors:** validation failures are `422` with
  `{"message": "...", "errors": {"field": ["..."]}}`. Other errors carry
  `{"message": "..."}`.

## Server and authentication

| Method | Path | Body | Answer |
|---|---|---|---|
| GET | `/server` | | `{name, content_locale, supported_locales, auth_methods, oidc}` |
| POST | `/auth/login` | `username`, `password`, `device_name` | `{token, user}` |
| POST | `/auth/oidc` | `id_token`, `device_name` | `{token, user}`; `404` without a provider |
| POST | `/auth/logout` | | `204`, revokes the token in use |
| GET | `/auth/user` | | User |

`auth_methods` lists `password` and, if a provider is configured, `oidc`.
`oidc` is `null` or `{issuer, client_id}`. Clients run the authorization code
flow with PKCE against the issuer themselves and exchange the resulting ID
token at `/auth/oidc`. `content_locale` is the language the household writes
recipes in.

**User:** `{id, username, name, email}`

## Realtime

| Method | Path | Answer |
|---|---|---|
| GET | `/realtime` | `{"data": {url, token}}`; `404` on servers without realtime |

Clients open `url` as a Server-Sent Events stream and send `token` as bearer
token in the `Authorization` header. Every event carries
`{"areas": [...]}` naming what changed: `shopping`, `pantry`, `plan`,
`recipes`, `products` or `tools`. Clients then reload that data. The token is
valid for twelve hours; on any error clients request a new one and reconnect.
The stream is served by the Mercure hub built into the web server, enabled by
setting `MERCURE_JWT_KEY`.

## Reference data

| Method | Path | Answer |
|---|---|---|
| GET | `/courses` | List of Course |
| GET | `/units` | List of Unit |
| GET | `/store-sections` | List of StoreSection, in walking order |
| GET | `/tags` | List of Tag |

- **Unit:** `{id, key, code, name, plural_name, kind, factor_to_base}`.
  `kind` is `mass`, `volume` or `product_specific`. `key` is `null` for units
  the household added.
- **StoreSection:** `{id, key, name, position}`
- **Tag:** `{id, type, key, name}`. `type` is `meal` or `tag`.
  Meal keys are `breakfast`, `lunch`, `dinner`, `dessert`, `snack`.
- **Course:** `{key, name}`. The main category of a dish: `starter`, `soup`,
  `salad`, `main`, `side`, `dessert`, `baking`, `snack` or `drink`. A recipe
  has at most one.

## Recipes

| Method | Path | Body or query | Answer |
|---|---|---|---|
| GET | `/recipes` | `?search=`, `?tag=<tag id>`, `?course=<key>`, `?favorite=1` | Paginated RecipeSummary |
| GET | `/recipes/{id}` | | Recipe |
| POST | `/recipes` | RecipeInput | Recipe, `201` |
| PATCH | `/recipes/{id}` | RecipeInput, all fields optional | Recipe |
| POST | `/recipes/{id}/cover` | multipart `image` | Recipe |
| PUT | `/recipes/{id}/rating` | `rating`: 0 to 10, or `null` to withdraw | RecipeSummary |
| PUT | `/recipes/{id}/favorite` | `favorite: bool` | RecipeSummary |
| DELETE | `/recipes/{id}` | | `204` |

- **RecipeSummary:** `{id, title, slug, description, course, course_name,
  servings, yield_text, prep_minutes, cook_minutes, cover_url, tags: [Tag],
  rating, is_favorite, average_rating, rating_count}`. `rating` (0 bad to 10
  good) and `is_favorite` belong to the signed-in user, `average_rating` is
  the mean over all household members with one decimal. The four fields are
  present on the recipe endpoints and absent where a recipe is embedded in
  another resource.
- **Recipe:** RecipeSummary plus `{source_url, source_platform,
  meal_tags_locked, ingredients: [Ingredient], steps: [Step], tools: [Tool]}`
- **Ingredient:** `{id, position, section, quantity, unit: Unit|null,
  product: Product|null, note, is_estimated, raw_text}`. A missing unit means
  the quantity counts pieces. Lines without a product only carry `raw_text`.
- **Step:** `{id, position, section, text}`. Consecutive steps with the same
  free-text `section` form a group, for example "Base", "Filling" and
  "Decoration" of a cake.
- **RecipeInput:** `title`, `description`, `course`, `servings`, `yield_text`,
  `prep_minutes`, `cook_minutes`, `source_url`, `tag_ids: [int]`,
  `tool_ids: [int]`, `ingredients: [{section, product_id, quantity, unit_id,
  note, is_estimated, raw_text}]`, `steps: [{section, text}]`. Lists that are
  present replace the stored ones as a whole. Sending `tag_ids` locks the tags
  against later changes by the AI.

## Tools

Tools form a catalog of the household, like products. Recipes refer to them
by id.

| Method | Path | Body or query | Answer |
|---|---|---|---|
| GET | `/tools` | `?search=` | List of Tool, not paginated |
| POST | `/tools` | `name`, `is_owned` | Tool, `201` |
| PATCH | `/tools/{id}` | `name`, `is_owned` | Tool |
| DELETE | `/tools/{id}` | | `204`; `409` if a recipe needs it |
| POST | `/tools/{id}/merge` | `into: <tool id>` | The remaining Tool |

**Tool:** `{id, name, is_owned, recipe_count}`. `is_owned` states whether the
household has the tool and defaults to `true`. `recipe_count` is present on
the tool endpoints only.

## Imports

| Method | Path | Body | Answer |
|---|---|---|---|
| POST | `/imports` | `url` | Import; `202` when queued, `200` when the video was imported before |
| GET | `/imports` | | The 30 most recent Imports |
| GET | `/imports/{id}` | | Import |
| GET | `/imports/{id}/wait` | | Import, as soon as it is completed or failed, at the latest after four minutes |
| POST | `/imports/{id}/retry` | | Import, `202`; `409` unless it failed |

**Import:** `{id, url, status, stage, error, error_message, created_at,
recipe: RecipeSummary|null}`. `status` is `pending`, `processing`, `completed`
or `failed`. While processing, `stage` is `downloading`, `analyzing` or
`saving`. Clients poll `GET /imports/{id}` to show progress, or hold one
request to `/imports/{id}/wait` open to learn the outcome.
`error` is `download_failed`, `no_recipe_found`, `ai_failed` or `unknown`.

## Household settings

| Method | Path | Body | Answer |
|---|---|---|---|
| GET | `/settings/household` | | HouseholdSettings |
| PATCH | `/settings/household` | `default_servings: 1–50` | HouseholdSettings |

**HouseholdSettings:** `{default_servings}`: the number of servings a meal is
planned and shopped for unless another number is chosen. Clients preset it
when planning and show recipes scaled to it. `2` until the household changes
it.

## Import settings

| Method | Path | Body | Answer |
|---|---|---|---|
| GET | `/settings/import` | | ImportSettings |
| PATCH | `/settings/import` | `identify_as_browser: bool` | ImportSettings |
| POST | `/settings/import/cookies` | multipart `cookies` | ImportSettings |
| DELETE | `/settings/import/cookies` | | ImportSettings |

**ImportSettings:** `{identify_as_browser, has_cookies}`. With
`identify_as_browser` web pages are fetched with the user agent of a browser,
for sites that turn the importer away. The cookie file is a Netscape format
export from a browser signed in to a video platform that only serves videos
to signed-in users; its content is never returned.

## Products and review

| Method | Path | Body or query | Answer |
|---|---|---|---|
| GET | `/products` | `?search=` (name or alias) | Paginated Product |
| GET | `/products/{id}` | | Product with conversions and packages |
| POST | `/products` | ProductInput | Product, `201` |
| PATCH | `/products/{id}` | ProductInput, all fields optional | Product |
| DELETE | `/products/{id}` | | `204`; `409` if a recipe uses it |
| GET | `/review` | | Paginated Product awaiting confirmation |
| POST | `/products/{id}/confirm` | | Product; confirms it with all conversions and packages |
| POST | `/products/{id}/merge` | `into: <product id>` | The remaining Product |
| PUT | `/products/{id}/conversions/{unit id}` | `amount_in_base`: number, or `null` for "cannot be converted" | Conversion |
| DELETE | `/products/{id}/conversions/{unit id}` | | `204` |
| POST | `/products/{id}/packages` | `label`, `amount_in_base` | Package, `201` |
| PATCH | `/product-packages/{id}` | `label`, `amount_in_base` | Package |
| DELETE | `/product-packages/{id}` | | `204` |
| POST | `/products/{id}/aliases` | `name` | Product |
| DELETE | `/product-aliases/{id}` | | `204` |

- **Product:** `{id, name, plural_name, icon, base_unit, is_pantry_staple,
  shelf_life_opened_days, status, enriched_at, recipe_count,
  store_section: StoreSection|null, aliases: [string],
  conversions: [Conversion], packages: [Package]}`. `status` is `suggested`
  or `confirmed`. `icon` is the key of a line icon for the shopping list
  tile (listed in `resources/icons/products/manifest.json`; clients show the generic icon of the store section, `section-<key>`, for products without one or with a key they do not know), or
  `null`. `recipe_count`, `conversions` and `packages` are only present on
  the detail and review endpoints.
- **Conversion:** `{id, unit: Unit, amount_in_base, status, source}`: one
  `unit` of the product equals `amount_in_base` of its base unit. An
  `amount_in_base` of `null` states that the unit cannot be converted for
  this product, as with pieces of fruit that vary in weight.
- **Package:** `{id, label, amount_in_base, status, source}`
- **ProductInput:** `name`, `plural_name`, `icon`, `store_section_id`, `base_unit`,
  `is_pantry_staple`, `shelf_life_opened_days`

Values entered through the API count as confirmed. `source` is `ai`, `user`
or `import`.

## Meal plan

| Method | Path | Body or query | Answer |
|---|---|---|---|
| GET | `/meal-plan` | `?from=&to=` | List of Entry |
| POST | `/meal-plan` | `date`, `meal`, `recipe_id`, `servings` (optional, default from the household settings) | Entry, `201` |
| PATCH | `/meal-plan/{id}` | any of the above, `cooked: bool` | Entry |
| DELETE | `/meal-plan/{id}` | | `204` |
| POST | `/meal-plan/suggest` | PlanRequest | List of Suggestion plus `meta.candidate_count`; nothing is saved |
| POST | `/meal-plan/batch` | `entries: [{date, meal, recipe_id, servings}]` | The Entries that were added, `201` |

**Entry:** `{id, date, meal, servings, cooked_at, recipe: RecipeSummary}`

**PlanRequest:**

- `from`, `to`: the period, at most 31 days. All its entries are taken into
  account.
- `slots: [{date, meal}]`: the days and meals to fill. Slots that already
  have an entry or for which no recipe matches are left out of the answer.
- `servings` (optional, default from the household settings)
- `preferences` (optional object of booleans): `use_leftovers` (default
  `true`) chooses recipes that use up opened packages and the pantry,
  `quick_on_weekdays` (`false`) only suggests recipes of at most 30 minutes
  from Monday to Friday, `prefer_not_recent` (`true`) favors recipes that were
  not planned for a long time, `prefer_favorites` (`false`) favors the user's
  favorites and well rated recipes, `variety` (`true`) avoids repeats and the
  same cuisine on consecutive days.
- `tag_ids: [int]`, `courses: [key]` (optional): only suggest recipes that
  carry one of the tags or belong to one of the courses.
- `keep: [{date, meal, recipe_id}]` (optional): suggestions the user locked.
  They are returned unchanged.
- `avoid: [{date, meal, recipe_id}]` (optional): recipes not to suggest for a
  slot again, used to re-roll a single meal.

**Suggestion:** `{date, meal, recipe: RecipeSummary, kept}`, ordered by day and
meal. Every call may answer with a different plan. `meta.candidate_count` is
the number of recipes that were eligible.

## Pantry

| Method | Path | Body | Answer |
|---|---|---|---|
| GET | `/pantry` | | List of PantryItem, soonest expiry first |
| POST | `/pantry` | `product_id`, `amount`, `opened_on`, `expires_on` | PantryItem, `201` |
| PATCH | `/pantry/{id}` | any of the above | PantryItem |
| DELETE | `/pantry/{id}` | | `204` |

**PantryItem:** `{id, amount, opened_on, expires_on, product: Product}`.
Without `expires_on` the expiry follows from the product's shelf life.

## Shopping lists

| Method | Path | Body | Answer |
|---|---|---|---|
| POST | `/shopping-lists` | `from`, `to` | ShoppingList; `201` when created, `200` when refreshed |
| GET | `/shopping-lists/current` | | ShoppingList of the latest period; `404` if none |
| GET | `/shopping-lists/{id}` | | ShoppingList |
| POST | `/shopping-lists/{id}/stock-leftovers` | | ShoppingList; `409` when done before |
| POST | `/shopping-lists/{id}/items` | `label` | Item, `201` |
| PATCH | `/shopping-list-items/{id}` | `checked: bool`, `label` | Item |
| DELETE | `/shopping-list-items/{id}` | | `204` |

- **ShoppingList:** `{id, starts_on, ends_on, leftovers_stocked_at,
  items: [Item]}`. Items are ordered by store section.
- **Item:** `{id, label, is_manual, needed_amount, purchase_amount,
  packages: [{package_id, label, amount, count}], leftover_amount,
  wasted_amount, uses: [{date, recipe_id, title, amount}], is_approximate,
  extra_amounts: [{quantity, unit_code}], checked_at, product: Product|null}`

Generated items name a product and the packages to buy. `leftover_amount`
remains usable after the period, `wasted_amount` expires before a recipe uses
it. `is_approximate` marks amounts based on unconfirmed conversions.
`extra_amounts` lists what recipes need in units that cannot be converted
into the base unit, to be bought in addition, for example 3 pieces. Items
without a product are manual entries or ingredient lines without a product.
Refreshing a list keeps manual items and the checked state of products.
