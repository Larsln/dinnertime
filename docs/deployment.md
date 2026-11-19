# Dinnertime hinter Traefik mit Authentik betreiben

Diese Anleitung bringt Dinnertime auf einen Server, auf dem Traefik als
Reverse Proxy läuft und Authentik als OpenID-Connect-Anbieter dient. Die
Namen sind Beispiele: `dinnertime.example.org` für Dinnertime,
`auth.example.org` für Authentik, `proxy` für das Docker-Netz von Traefik
und `websecure` / `letsencrypt` für Entrypoint und Zertifikatsauflöser.
Ersetze sie durch deine eigenen.

## Wie die Teile zusammenspielen

- Traefik nimmt HTTPS an und reicht alles an den Container `app` weiter
  (FrankenPHP auf Port 80). Dort laufen Weboberfläche, API und der
  Echtzeit-Hub unter `/.well-known/mercure`.
- `queue` verarbeitet Importe und KI-Anreicherungen, `scheduler` die
  täglichen Aufräumarbeiten, `db` ist Postgres. Diese drei brauchen kein
  Routing.
- Die Anmeldung über Authentik läuft im Browser bzw. in der App selbst
  (Authorization Code mit PKCE). Dinnertime prüft danach nur das ID-Token.
  Konten entstehen beim ersten Login automatisch.

**Kein Forward-Auth davor.** Anders als bei Diensten ohne eigene Anmeldung
darf vor Dinnertime keine Authentik-Outpost- oder Forward-Auth-Middleware
hängen. Die iOS-App und die API arbeiten mit eigenen Tokens; ein
vorgeschaltetes Login würde sie aussperren. Wer Dinnertime nutzen darf,
regelst du stattdessen über die Bindung der Anwendung in Authentik (unten).

## 1. Image

Die CI baut bei jedem Push auf `main` das öffentliche Image
`ghcr.io/larsln/dinnertime:latest` für `linux/amd64` und `linux/arm64`.
`compose.production.yaml` nutzt es schon; du musst nichts selbst bauen.

Wer lieber selbst baut, etwa mit eigenen Änderungen:

```sh
docker buildx build --target production --platform linux/amd64 \
    -t dinnertime:latest --load .
docker save dinnertime:latest | ssh server docker load
```

Dann in der Compose-Datei `ghcr.io/larsln/dinnertime:latest` durch
`dinnertime:latest` ersetzen.

## 2. Anwendung in Authentik anlegen

1. **Provider anlegen:** *Applications → Providers → Create →
   OAuth2/OpenID Provider*.
   - *Name:* `Dinnertime`
   - *Authorization flow:* dein üblicher (z. B. implicit consent)
   - *Client type:* **Public**. Dinnertime nutzt PKCE und kennt kein
     Client-Secret.
   - *Redirect URIs* (beide, Modus *Strict*):
     - `https://dinnertime.example.org/oidc/callback` für die Weboberfläche
     - `dinnertime://oidc` für die iOS-App
   - *Signing Key:* **ein Zertifikat auswählen**, z. B. *authentik
     Self-signed Certificate*. Ohne Zertifikat signiert Authentik mit HS256,
     Dinnertime erwartet RS256 und lehnt das Token sonst ab.
   - *Scopes:* `openid`, `profile`, `email` (Voreinstellung).
   - *Subject mode:* Voreinstellung (hashed user ID) ist richtig.
2. **Anwendung anlegen:** *Applications → Applications → Create*, Provider
   `Dinnertime`, Slug z. B. `dinnertime`, Launch URL
   `https://dinnertime.example.org`.
3. **Zugriff beschränken:** unter der Anwendung → *Policy / Group / User
   Bindings* eine Gruppe binden, etwa `dinnertime`, und dich und deine
   Mitbewohner dort eintragen. Ohne Bindung darf jedes Authentik-Konto sich
   anmelden und bekommt damit ein Konto in deinem Haushalt.
4. Notiere **Client ID** und den **Issuer**. Der Issuer hat die Form
   `https://auth.example.org/application/o/dinnertime/` (mit Slash am Ende,
   genau so wie er im Provider unter *OpenID Configuration Issuer* steht).

Der Benutzername in Dinnertime kommt aus `preferred_username`, der Name aus
`name`, die E-Mail aus `email`.

## 3. Verzeichnis und `.env` auf dem Server

```sh
mkdir -p /srv/dinnertime && cd /srv/dinnertime
# aus dem Repository kopieren:
#   compose.production.yaml  →  compose.yaml
#   .env.production.example  →  .env
```

In der `.env`:

| Eintrag | Wert |
|---|---|
| `APP_URL` | `https://dinnertime.example.org` |
| `APP_KEY` | erzeugen mit `docker compose run --rm app php artisan key:generate --show` |
| `DB_PASSWORD` | ein langes Zufallspasswort |
| `MERCURE_JWT_KEY` | `openssl rand -base64 48`; aktiviert die Echtzeit-Aktualisierung |
| `OIDC_ISSUER` | der Issuer aus Schritt 2, z. B. `https://auth.example.org/application/o/dinnertime/` |
| `OIDC_CLIENT_ID` | die Client ID aus Schritt 2 |
| `GEMINI_API_KEY` | für Importe aus Videos und Webseiten und die Produktvorschläge; ohne Key bleiben Importe leere Rezepte mit Link |
| `APP_CURRENCY` | `EUR`, sonst der ISO-Code deiner Währung |
| `MEALIE_URL`, `MEALIE_TOKEN` | nur für den einmaligen Umzug aus Mealie (Schritt 7) |

## 4. Compose-Datei an Traefik anschließen

Entferne in `compose.yaml` beim Dienst `app` den Block `ports` und ergänze
Netz und Labels. Die übrigen Dienste bleiben, wie sie sind:

```yaml
services:
    app:
        # … wie in compose.production.yaml, ohne "ports"
        networks:
            - default
            - proxy
        labels:
            - traefik.enable=true
            - traefik.docker.network=proxy
            - traefik.http.routers.dinnertime.rule=Host(`dinnertime.example.org`)
            - traefik.http.routers.dinnertime.entrypoints=websecure
            - traefik.http.routers.dinnertime.tls.certresolver=letsencrypt
            - traefik.http.services.dinnertime.loadbalancer.server.port=80

networks:
    proxy:
        external: true
```

Hinweise:

- `traefik.docker.network` ist nötig, weil `app` in zwei Netzen hängt.
- Die Echtzeit-Verbindung ist ein Server-Sent-Events-Strom auf derselben
  Domain. Traefik reicht ihn ohne weitere Einstellung durch; eine
  `compress`-Middleware ist unschädlich, sie lässt `text/event-stream` aus.
- Dinnertime vertraut den Weiterleitungs-Headern des Proxys, die Links in
  der App entstehen deshalb mit `https`.

## 5. Starten und prüfen

```sh
docker compose up -d
docker compose logs -f app      # Migrationen und Seed laufen beim ersten Start
curl https://dinnertime.example.org/up
curl https://dinnertime.example.org/api/v1/server
```

`/api/v1/server` muss `"auth_methods":["password","oidc"]` und den Issuer
nennen. Fehlt `oidc`, sind `OIDC_ISSUER` oder `OIDC_CLIENT_ID` leer.

Danach im Browser `https://dinnertime.example.org` öffnen und *Mit
Identity-Provider anmelden* wählen. Das erste Login legt das Konto an.

Ein lokales Konto ohne Authentik, etwa als Notzugang, legst du so an:

```sh
docker compose exec app php artisan user:create notfall --name="Notzugang"
```

## 6. iOS-App verbinden

In der App die Serveradresse `https://dinnertime.example.org` eintragen. Die
App erkennt Authentik und bietet die Anmeldung an; Authentik leitet über
`dinnertime://oidc` zurück. Ist der Server nur im Heimnetz oder per VPN
erreichbar, funktioniert die App unterwegs im Offline-Modus mit dem
letzten Stand.

## 7. Daten übernehmen

### Aus Mealie

`MEALIE_URL` (z. B. `https://mealie.example.org`) und `MEALIE_TOKEN` (API-Token
aus Mealie, *Profil → API Tokens*) in die `.env`, dann:

```sh
docker compose up -d
docker compose exec app php artisan mealie:import
```

Der Import ist wiederholbar und gleicht über die Mealie-IDs ab; `--force`
überschreibt bereits übernommene Einträge. Danach kannst du die beiden
Einträge wieder leeren.

### Aus einer Testinstallation

Datenbank und Dateien (Titelbilder, hochgeladene Import-Cookies) wandern
getrennt. Benutzer und Datenbank auf dem Server sind die aus deiner `.env`,
im Beispiel `mealplanner`:

```sh
# auf dem alten Rechner
docker compose exec -T db pg_dump -U mealplanner -Fc mealplanner > dinnertime.dump
docker compose exec -T app tar -C /app/storage/app -cf - . > storage.tar

# auf dem Server, vor dem ersten Login
docker compose up -d db
docker compose exec -T db pg_restore -U mealplanner -d mealplanner --clean --if-exists < dinnertime.dump
docker compose up -d
docker compose exec -T app tar -C /app/storage/app -xf - < storage.tar
```

Bewertungen, Favoriten und Abstimmungen hängen am Konto. Damit dein
Authentik-Login das alte Konto übernimmt statt ein neues anzulegen, benenne
das alte Konto vor dem ersten Login auf deinen Authentik-Benutzernamen um:

```sh
docker compose exec app php artisan tinker --execute="App\Models\User::where('username', 'demo')->update(['username' => 'dein-authentik-name'])"
```

Beim ersten Login verknüpft Dinnertime ein Konto ohne Authentik-Bindung mit
gleichem Benutzernamen, danach gilt nur noch die Authentik-ID.

## 8. Updates und Sicherung

Update:

```sh
docker compose pull      # oder ein neues Image wie in Schritt 1 laden
docker compose up -d     # Migrationen laufen beim Start
```

Sicherung: die Volumes `db-data` (oder besser regelmäßig `pg_dump` wie
oben) und `storage` mit Titelbildern und Import-Cookies. Die `.env` gehört
mit dazu.

## Fehlersuche

| Beobachtung | Ursache |
|---|---|
| Kein Authentik-Knopf beim Login | `OIDC_ISSUER` oder `OIDC_CLIENT_ID` fehlen; `/api/v1/server` prüfen |
| Authentik meldet „Redirect URI Error" | Redirect-URI nicht exakt eingetragen, Schreibweise und Slash prüfen |
| Nach dem Login „Die Anmeldung beim Identity-Provider konnte nicht bestätigt werden." | kein Signing Key im Provider gewählt (HS256), oder der Issuer in der `.env` weicht vom Issuer im Token ab |
| Im Browser bleibt die Anmeldung beim Zurückkehren hängen | Token-Anfrage an Authentik vom Browser blockiert; Redirect-URI der Weboberfläche muss im Provider stehen, damit Authentik die Herkunft zulässt |
| Änderungen erscheinen erst nach Neuladen | `MERCURE_JWT_KEY` leer, oder eine Middleware puffert Antworten |
| Importe bleiben auf „Wartet" | Container `queue` läuft nicht: `docker compose logs queue` |
