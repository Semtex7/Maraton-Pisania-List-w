# Wdrożenie (WP Engine)

Stan: 2026-10-14, `develop` @ `02ddaac` + zmiany robocze opisane w [status.md](status.md#zmiany-robocze-niezatwierdzone).

## Co jest ustalone

| Element | Stan | Źródło |
|---|---|---|
| Staging | istnieje na WP Engine | **Decyzja** (właściciel projektu, 2026-10-14) |
| Produkcja | powstanie ze stagingu WP Engine w tygodniu od 2026-10-19 | **Decyzja** (właściciel projektu, 2026-10-14) |
| Repozytorium | `origin` = GitHub, gałąź `develop`; obejmuje tylko `wp-content` | `git remote -v` |
| Konfiguracja deployu w repo | brak (żadnego CI, workflow ani skryptu); `.wpe-push-ignore` i `.wpe-pull-ignore` w katalogu WordPressa są puste | pliki w `app/public/` |

**Otwarta kwestia:** nie jest udokumentowane, jak kod trafia z repozytorium na staging (git push do WP Engine, SFTP, Local Connect czy inna metoda). Ta część musi zostać uzupełniona przez osobę, która wykonuje deploy.

## Co musi trafić na środowisko

Pliki (z repozytorium, katalog `wp-content`):

- `plugins/` — w tym `amnesty-petitions-mk` i zależności (Elementor, Elementor Pro, ACF);
- `themes/hello-elementor/`, `themes/hello-elementor-child/` — z aktualnym `dist/` (build nie jest uruchamiany na serwerze; patrz [hello-elementor-child.md](hello-elementor-child.md#zasoby-i-build)).

Baza danych (nie ma jej w repozytorium):

- tabela `{prefix}amnesty_signatures`;
- wpisy `amnesty_petition`, `wydarzenie`, `material_order`, strony z shortcode'ami (m.in. strona o slugu `panel`), menu z pozycjami „Zaloguj się”, „Zarejestruj się”, „Panel” (dopasowywanymi po tytule);
- opcje pluginu i motywu (lista nazw: [architecture.md](architecture.md#opcje-wordpressa-nazwy-bez-wartości)), w tym sekrety Salesforce.

## Tabela podpisów na nowym środowisku

Fakt: tabela jest tworzona wyłącznie przez `dbDelta()` w hooku aktywacji pluginu ([`database.php`](../plugins/amnesty-petitions-mk/includes/database.php)). Plugin nie sprawdza istnienia tabeli ani wersji schematu przy zwykłym ładowaniu.

Sposoby uzyskania tabeli:

1. **Kopia środowiska** (staging → produkcja w WP Engine) — kopiowana jest cała baza, więc tabela i podpisy przechodzą razem z nią. Dane testowe ze stagingu trzeba wtedy świadomie usunąć lub zostawić.
2. **Ponowna aktywacja pluginu** (Wtyczki → Dezaktywuj → Aktywuj, albo `wp plugin deactivate amnesty-petitions-mk && wp plugin activate amnesty-petitions-mk`) — tworzy brakującą tabelę lub dodaje brakujące kolumny; istniejących danych nie usuwa.

Kontrola po wdrożeniu (WP-CLI):

```bash
wp db query "SHOW TABLES LIKE '%amnesty_signatures'"
wp db query "DESCRIBE $(wp db prefix)amnesty_signatures"
```

Każda zmiana schematu w `database.php` wymaga kroku 2 na każdym środowisku.

**Propozycja:** dodać do pluginu sprawdzanie wersji schematu (opcja z numerem wersji + `dbDelta` przy zmianie), aby nie polegać na ręcznej reaktywacji.

## Proponowana lista kontrolna przed uruchomieniem produkcji

Status: **Propozycja** — nieuzgodniona, do weryfikacji przez osobę wdrażającą.

1. Zacommitować i wypchnąć zmiany robocze (po przeglądzie ryzyk z [status.md](status.md)); przebudować `dist/` motywu.
2. Wdrożyć kod na staging i sprawdzić: podpis petycji, logowanie, rejestrację, dodanie wydarzenia, mapę, zamówienie materiałów (metabox w adminie), raport PDF.
3. Zmigrować flagę `widoczne_na_mapie` dla istniejących wydarzeń (lub świadomie zostawić je poza mapą).
4. Utworzyć produkcję ze stagingu; zdecydować, czy usuwamy testowe podpisy, wydarzenia i zamówienia.
5. Na produkcji ustawić dane Salesforce produkcyjnego (opcje `amnesty_sf_*`) zamiast sandboxa — o ile staging używa sandboxa (niezweryfikowane).
5a. Ustawić daty edycji (Petitions → Ustawienia → Edycja). Bez nich liczniki i „Podpisz wszystkie” używają roku kalendarzowego, więc 1 stycznia liczniki się wyzerują. W widgecie licznika wydarzeń na stronie głównej ustawić „Źródło danych” = „Pobierz liczbę wydarzeń”.
5b. Sprawdzić wysyłkę e-maili z WordPressa (link aktywacyjny po rejestracji, reset hasła) — bez działającej poczty nowe konta nie dadzą się aktywować.
6. Sprawdzić tabelę podpisów (komendy wyżej) i flush permalinków (Ustawienia → Bezpośrednie odnośniki → Zapisz), jeśli `/petycja/…` zwraca 404.
7. Wyłączyć wyświetlanie błędów na produkcji (`WP_DEBUG_DISPLAY` = false); lokalnie `WP_DEBUG_LOG` zapisuje `wp-content/debug.log`, który jest publicznie dostępny, jeśli serwer go nie blokuje.
8. Sprawdzić, że log pluginu trafia do `_wpeprivate/amnesty-debug.log` (lub do logu PHP) i nie jest dostępny z przeglądarki.

## Log pluginu

Po zmianie roboczej z 2026-10-14 `amnesty_custom_log()` zapisuje do `ABSPATH . '_wpeprivate/amnesty-debug.log'` (katalog można zmienić stałą `AMNESTY_PETITIONS_LOG_DIR` w `wp-config.php`). Gdy katalog nie istnieje lub nie jest zapisywalny, wpis trafia do logu PHP przez `error_log()`.

- WP Engine: `_wpeprivate` jest katalogiem prywatnym (brak dostępu z sieci) — **założenie** na podstawie konwencji WP Engine, do sprawdzenia na stagingu (punkt 8).
- Local: konfiguracja nginx w `conf/nginx` nie blokuje `_wpeprivate`, więc lokalnie plik jest dostępny przez HTTP. Dotyczy tylko maszyny deweloperskiej.
