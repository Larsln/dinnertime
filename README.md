# Mealplanner

Recipe, meal plan and shopping list system. Laravel backend with a Vue web
client; the iOS app lives in a separate repository.

The design is documented in [docs/plan.md](docs/plan.md).

## Local setup

Everything runs in Docker Compose, no PHP or Node installation is required.

```sh
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
docker compose exec app php artisan user:create
```

The API is served at <http://localhost:8000/api/v1>. Every instance is one
household; all users created with `user:create` share its data.

## Importing from Mealie

Set `MEALIE_TOKEN` in `.env`, then run:

```sh
docker compose exec app php artisan mealie:import
```

The import can be repeated. Records that were imported before are left
untouched unless `--force` is passed.

## Checks

```sh
docker compose exec app vendor/bin/pint
docker compose exec app vendor/bin/phpstan analyse
docker compose exec app php artisan test
```
