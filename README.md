# Dinnertime

Self-hosted recipe, meal plan and shopping list system for one household.

This repository holds the server and the web client. The native iOS app lives
in [dinnertime-ios](https://github.com/Larsln/dinnertime-ios).

- **Import recipes** from TikTok, Instagram, YouTube or any web page. The whole
  video is analysed together with its caption.
- **Products instead of free text.** Every ingredient line refers to a product
  with store section, package sizes, shelf life and unit conversions.
- **Shopping lists in packages**, ordered by the route through the store, with
  the leftovers each purchase causes.
- **Leftover-aware meal plan.** The generator picks recipes that use up opened
  packages before they spoil.
- **AI suggests, you confirm.** Conversions, package sizes and shelf life are
  proposed by Gemini and confirmed with one tap. Without an API key everything
  still works, only import and suggestions are unavailable.
- **German and English** interface. Recipes are written in the language of the
  household.

There is no central service: every household runs its own server. The iOS app
lives in a separate repository and connects to the server of your choice.

Further reading: [API](docs/api.md) and the German
[design notes](docs/plan.md).

## Running your own server

A step-by-step guide for running Dinnertime behind Traefik with Authentik as
sign-in provider, including moving over from Mealie, is in
[docs/deployment.md](docs/deployment.md) (German).

You need Docker with the Compose plugin.

1. Copy `compose.production.yaml` and `.env.production.example` to a directory
   on your server and rename them to `compose.yaml` and `.env`.
2. Edit `.env`: set `APP_URL`, a database password and `APP_LOCALE` (`de` or
   `en`, the language recipes are written in). Generate the application key
   and put it into `APP_KEY`:

   ```sh
   docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
   ```

3. Start the stack and create the first household member:

   ```sh
   docker compose up -d
   docker compose exec app php artisan user:create
   ```

The server listens on port 8000 without TLS. Put it behind a reverse proxy
that terminates HTTPS. The database and uploaded images live in the volumes
`db-data` and `storage`; back both up.

### Optional settings

| Setting | Purpose |
|---|---|
| `GEMINI_API_KEY` | Enables recipe import and AI suggestions. Keys are available from Google AI Studio. |
| `GEMINI_VIDEO_MODEL`, `GEMINI_TEXT_MODEL` | Models for video analysis and for small tasks. |
| `OIDC_ISSUER`, `OIDC_CLIENT_ID` | Offers sign-in with an OpenID Connect provider next to local accounts. Register a public client with PKCE and the redirect URIs `<APP_URL>/oidc/callback` and `dinnertime://oidc`. |
| `PASSWORD_LOGIN` | `false` turns off sign-in with username and password, so only the OpenID Connect provider remains. |
| `KEEP_VIDEOS`, `KEEP_VIDEOS_MAX_MB` | Imported videos are copied into `storage` so recipes outlive their source; `false` turns that off, and videos above the size limit (300 MB) are not kept. The videos of recipes imported earlier are fetched once a week, or at once with `php artisan recipes:fetch-missing-videos`. |
| `YTDLP_COOKIES_FILE` | Path inside the container to a Netscape cookie file, for platforms that only serve videos to signed-in users. |

### Updating

```sh
docker compose pull
docker compose up -d
```

Database migrations run when the `app` container starts.

## Development

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

The application is served at <http://localhost:8000> and under the same
port on the address of the machine, so phones in the same network reach it
too. The `vite` container rebuilds the frontend whenever a file changes.

### Checks

```sh
docker compose exec app vendor/bin/pint
docker compose exec app vendor/bin/phpstan analyse
docker compose exec app php artisan test
docker compose exec vite npm run format
docker compose exec vite npm run lint
docker compose exec vite npm run typecheck
```

Tests use the database `mealplanner_test`, which the `db` container creates
on its first start.

### Importing from Mealie

Set `MEALIE_URL` and `MEALIE_TOKEN` in `.env`, then run:

```sh
docker compose exec app php artisan mealie:import
```

The import can be repeated. Records that were imported before are left
untouched unless `--force` is passed.

## License

[GNU Affero General Public License v3.0](LICENSE)
