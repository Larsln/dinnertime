# Detailplan: Rezept- und Einkaufssystem

Stand 2026-11-17. Arbeitsname `mealplanner`, bis der endgültige Name feststeht.

## 0. Leitlinien

- **Open Source und selbst gehostet.** Server und iOS-App sind frei verfügbar,
  jeder betreibt seinen eigenen Server. Es gibt keinen zentralen Dienst.
- **Eine Instanz ist ein Haushalt.** Alle Konten einer Instanz teilen Rezepte,
  Wochenplan, Vorrat und Einkaufsliste gleichberechtigt.
- **Nichts Installationsspezifisches im Code.** Domains, Identity-Provider und
  Schlüssel kommen ausschließlich aus der Konfiguration.
- **Betrieb nur in Docker**, lokal wie produktiv. Ein veröffentlichtes Image
  und eine Compose-Vorlage genügen für die Installation.
- **Mehrsprachig von Anfang an.** Eingebaute Stammdaten (Einheiten, Bereiche,
  Tags) haben sprachneutrale Schlüssel und werden über Sprachdateien benannt;
  die Oberfläche folgt dem `Accept-Language`-Header. Rezepte und Produkte
  schreibt der Haushalt in seiner Inhaltssprache (`APP_LOCALE`), in der auch
  die KI antwortet. Die KI liefert Einheiten und Tags als Schlüssel.
- **KI ist optional.** Der Betreiber hinterlegt einen eigenen Gemini-Key; ohne
  Key bleibt die App vollständig bedienbar, nur Import und Vorschläge entfallen.

## 1. Repos und lokale Umgebung

| Repo | Inhalt | Lokal |
|---|---|---|
| `mealplanner` | Laravel-Backend + Vue-Web | `~/mealplanner/backend` |
| `mealplanner-ios` | SwiftUI-App + Share Extension | `~/mealplanner/ios` |

Lokal läuft alles in Docker Compose, damit es dem späteren Deployment entspricht
und auf dem Mac weder PHP noch Node installiert werden müssen:

| Dienst | Aufgabe |
|---|---|
| `app` | FrankenPHP mit Laravel, dazu yt-dlp, ffmpeg, Imagick |
| `queue` | `queue:work` für die Queues `default` und `imports` |
| `scheduler` | `schedule:work` |
| `db` | Postgres 17 |
| `vite` | Node, Vite-Dev-Server für das Vue-Frontend |

Für den Betrieb gibt es ein Produktions-Image (über GHCR veröffentlicht) und
eine `compose.production.yaml` als Vorlage für Selbsthoster.

## 2. Datenmodell

Mengen werden intern immer in der **Basiseinheit des Produkts** gerechnet
(`g`, `ml` oder `piece`). Alles, was die KI liefert, trägt einen Status
`suggested` oder `confirmed`.

### Stammdaten

| Tabelle | Wichtige Felder | Zweck |
|---|---|---|
| `users` | `username`, `name`, `email`, `password`, `oidc_sub` | Haushaltsmitglied; lokales Konto mit Passwort oder Konto eines OIDC-Providers |
| `store_sections` | `key` oder `name`, `position` | Supermarkt-Bereiche in Laufreihenfolge; eingebaute per Schlüssel, eigene mit Namen |
| `units` | `key` oder `code`/`name`, `kind` (`mass`, `volume`, `product_specific`), `factor_to_base` | g, kg, ml, l fest umrechenbar; EL, TL, Prise, Bund, Zehe, Dose, Becher, Päckchen, Stück nur über das Produkt |
| `products` | `name`, `store_section_id`, `base_unit`, `is_pantry_staple`, `shelf_life_opened_days`, `shelf_life_status`, `status`, `mealie_food_id` | Ein kaufbares Produkt |
| `product_aliases` | `product_id`, `name` (eindeutig) | Schreibweisen und Synonyme |
| `product_conversions` | `product_id`, `unit_id`, `amount_in_base`, `status`, `source` | „1 EL Mehl = 10 g", eindeutig je Produkt und Einheit |
| `product_packages` | `product_id`, `label`, `amount_in_base`, `status`, `source` | Typische Packungsgrößen |

### Rezepte

| Tabelle | Wichtige Felder | Zweck |
|---|---|---|
| `recipes` | `title`, `slug`, `description`, `servings` (Zahl), `yield_text`, `prep_minutes`, `cook_minutes`, `source_url`, `source_platform`, `source_video_id`, `cover_image_path`, `meal_tags_locked` | Rezept; Duplikate über `source_platform` + `source_video_id` |
| `recipe_ingredients` | `recipe_id`, `position`, `section`, `product_id`, `quantity`, `unit_id`, `note`, `is_estimated`, `raw_text` | Zutatenzeile, zeigt auf ein Produkt |
| `recipe_steps` | `recipe_id`, `position`, `title`, `text` | Arbeitsschritt, Titel nur am Beginn einer Komponente |
| `tags` | `type` (`meal`, `category`, `tag`), `key` | Feste Listen, per Seeder, Namen aus Sprachdateien |
| `recipe_tag` | `recipe_id`, `tag_id` | Zuordnung |
| `tools`, `recipe_tool` | `name` | Utensilien, deutsch, Singular |
| `recipe_imports` | `url`, `platform`, `video_id`, `status`, `attempts`, `error`, `recipe_id`, `user_id` | Importauftrag mit Verlauf und Fehlermeldung |

### Planung und Einkauf

| Tabelle | Wichtige Felder | Zweck |
|---|---|---|
| `meal_plan_entries` | `date`, `meal_type`, `recipe_id`, `servings`, `cooked_at` | Wochenplan |
| `pantry_items` | `product_id`, `amount_in_base`, `opened_on`, `expires_on` | Angebrochene Reste mit Datum |
| `shopping_lists` | `starts_on`, `ends_on`, `status` | Eine Liste je Planungszeitraum |
| `shopping_list_items` | `product_id`, `label`, `needed_amount`, `package_id`, `package_count`, `leftover_amount`, `leftover_used_on`, `leftover_recipe_id`, `checked_at`, `checked_by` | Position in Packungen, Rest mit Verwendungshinweis; `label` für freie Einträge |

## 3. API

REST mit JSON unter `/api/v1`. Web und iOS melden sich gleich an und erhalten
vom Server ein eigenes API-Token (Laravel Sanctum):

- **Lokales Konto:** Benutzername und Passwort. Das erste Konto legt der
  Betreiber mit `php artisan user:create` an, eine offene Registrierung gibt
  es nicht.
- **OIDC (optional):** Ist ein Provider konfiguriert (Issuer, Client-ID), bietet
  der Server zusätzlich die Anmeldung per PKCE an und tauscht das geprüfte
  ID-Token gegen ein API-Token.
- `GET /server` ist öffentlich und nennt Name und angebotene Anmeldearten.
  Die iOS-App prüft damit die eingegebene Server-URL.

| Bereich | Endpunkte |
|---|---|
| Anmeldung | `GET /server`, `POST /auth/login`, `POST /auth/logout`, `GET /auth/user` |
| Rezepte | `GET/POST /recipes`, `GET/PATCH/DELETE /recipes/{id}`, Filter nach Tag, Suche |
| Import | `POST /imports` (URL), `GET /imports`, `GET /imports/{id}`, `POST /imports/{id}/retry` |
| Produkte | `GET/POST /products`, `PATCH /products/{id}`, `POST /products/{id}/merge`, Aliase, Umrechnungen, Packungen |
| Prüf-Liste | `GET /review`, `POST /review/{type}/{id}/confirm`, `PATCH` zum Korrigieren, `DELETE` zum Verwerfen |
| Wochenplan | `GET /meal-plan?from&to`, `POST/PATCH/DELETE /meal-plan/{id}`, `POST /meal-plan/generate` |
| Einkauf | `GET /shopping-lists/current`, `POST /shopping-lists` (aus Plan erzeugen), `PATCH /shopping-list-items/{id}` (abhaken) |
| Vorrat | `GET/POST /pantry`, `PATCH/DELETE /pantry/{id}` |
| Stammdaten | `GET /units`, `GET /store-sections`, `GET /tags` |

Die Share Extension ruft nur `POST /imports` auf und ist danach fertig.

## 4. KI-Jobs und Prüf-Liste

Gemini wird ausschließlich über einen `GeminiClient` angesprochen
(`generateContent` mit `responseSchema`, Files-API für Videos).

| Job | Queue | Ablauf |
|---|---|---|
| `ImportVideoRecipe` | `imports`, Timeout 300 s | URL auflösen → `yt-dlp -J` → Duplikat über Video-ID prüfen → Download (720p, 3 Versuche mit Backoff) → Upload, warten solange `PROCESSING` → Analyse mit `gemini-3.8-flash` → Datei löschen → Zutaten auf Produkte abbilden → Titelbild wählen |
| `ImportWebRecipe` | `imports` | Seite laden, schema.org-Daten und Text an Gemini, gleiche Ausgabeform |
| `ChooseCoverImage` | `imports` | Schärfstes Frame je Zeitpunkt (±0,6 s), Auswahl mit `gemini-3.5-flash-lite` |
| `EnrichProduct` | `default` | Für neue Produkte: Bereich, Vorrat ja/nein, Haltbarkeit, Packungen, Umrechnungen für alle Einheiten, die in Rezepten vorkommen |
| `ClassifyRecipeMeals` | `default` | Mahlzeiten-Tags, nur wenn `meal_tags_locked` nicht gesetzt ist |

Regeln:

- Die bewährten Prompts kommen aus `mealie-video-importer` (`video_recipe.py`).
- `PROHIBITED_CONTENT` und leere Antworten werden abgefangen und bis zu
  dreimal wiederholt; danach steht im Import eine verständliche Meldung.
- Zutaten → Produkt: erst exakter Treffer auf Name oder Alias, sonst legt der
  Import ein Produkt mit Status `suggested` an. Nichts wird stillschweigend
  zusammengeführt (Nutzerregel: nur Schreibweisen, nie Produktdetails).
- Die Prüf-Liste ist keine eigene Tabelle, sondern eine Abfrage über alles mit
  Status `suggested`: neue Produkte, Umrechnungen, Packungen, Haltbarkeit.
  Ein Tipp bestätigt, Korrigieren setzt den Wert und bestätigt zugleich.
- Solange eine Umrechnung fehlt oder unbestätigt ist, rechnet die
  Einkaufsliste mit dem Vorschlag und markiert die Position.

## 5. Planer und Einkaufsliste

### Einkaufsliste

1. Bedarf je Produkt aus allen Planeinträgen in Basiseinheit summieren
   (Portionen skaliert, Umrechnung über das Produkt).
2. Vorrats-Produkte (`is_pantry_staple`) weglassen, Bestand aus `pantry_items`
   abziehen.
3. Auf Packungen runden: die Kombination mit dem kleinsten Rest, bei Gleichstand
   die mit weniger Packungen (kleine dynamische Programmierung).
4. Rest ausweisen und, falls ein späteres Rezept im Plan ihn verbraucht,
   Tag und Rezept dazuschreiben.
5. Nach `store_sections.position` sortieren.

### Reste-Planer

- **Eingabe:** Zeitraum, Slots (Mahlzeit, Portionen), fixierte Einträge, Vorrat.
- **Kandidaten:** Rezepte mit passendem Mahlzeiten-Tag.
- **Kosten eines Plans:** Summe über alle Produkte aus
  `Rest × Verderb-Gewicht`, wobei ein Rest nur zählt, wenn er nicht innerhalb
  seiner Haltbarkeit von einem späteren Rezept verbraucht wird. Dazu Strafen
  für Wiederholungen im Zeitraum und für kürzlich gekochte Rezepte.
- **Suche:** zufälliger Start, dann lokale Suche (ein Slot wird getauscht, die
  Änderung bleibt, wenn die Kosten sinken), mehrere Neustarts. Bei wenigen
  Dutzend Rezepten läuft das in Millisekunden.
- Die Kostenfunktion nutzt denselben `ShoppingListBuilder` wie die
  Einkaufsliste, damit Plan und Liste nie auseinanderlaufen.

## 6. Mealie-Import

Artisan-Befehl `mealie:import` (wiederholbar, gleicht über Mealie-IDs ab):

1. Etiketten → `store_sections` (Reihenfolge aus der Übergabe).
2. Einheiten → `units` (abgeglichen über `code`).
3. Lebensmittel → `products` mit Aliasen und Bereich, Status `confirmed`.
4. Rezepte mit Zutaten, Schritten, Tags, Utensilien, Portionszahl und Titelbild.
5. Für alle Produkte `EnrichProduct` einreihen, damit die Prüf-Liste gefüllt ist.

Zugang über `MEALIE_URL` und `MEALIE_TOKEN` in `.env`.

## 7. Ausbaustufen

| Stufe | Inhalt | Fertig, wenn |
|---|---|---|
| 1 | Docker-Umgebung, Datenmodell, Seeder, Mealie-Import, Rezept- und Produkt-API, Anmeldung mit lokalen Konten | Rezepte sind nach Anmeldung per API abrufbar |
| 2 | Gemini-Client, `EnrichProduct`, Prüf-Liste (API + Vue), Anmeldung per OIDC | Vorschläge lassen sich im Web bestätigen |
| 3 | Einkaufsliste in Packungen, Wochenplan, Reste-Planer, Vorrat | Eine Woche lässt sich planen und einkaufen |
| 4 | Video- und Web-Import in Laravel | Ein TikTok-Link ergibt ein fertiges Rezept |
| 5 | iOS-App mit Share Extension | Teilen aus TikTok legt den Import an |
| 6 | Produktions-Image, Compose-Vorlage, Installationsanleitung, Veröffentlichung | Ein Fremder kann den Server nach Anleitung starten |

## 8. Offene Punkte

- **Name** für App und Repos (bis dahin Arbeitsname `mealplanner`).
- **Lizenz** für Server und iOS-App.
