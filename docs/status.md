# Stan projektu, decyzje i otwarte kwestie

Stan na: 2026-10-14  
Gałąź: `develop` (śledzi `origin/develop`)  
HEAD: `02ddaac` — „Add event report PDF draft” (2026-10-01)  

## Etap projektu

Strona działa lokalnie (Local) i na stagingu (WP Engine). Produkcja nie istnieje — ma powstać ze stagingu na WP Engine w tygodniu od 2026-10-19 (**Decyzja**, właściciel projektu, 2026-10-14).

Zmiany od 2026-10-01 są wyłącznie w drzewie roboczym (niezacommitowane); czy trafiły na staging — niezweryfikowane. Lista zadań do startu: [requirements.md](requirements.md).

## Kontrole z 2026-10-14

Wykonane na drzewie roboczym, na działającej stronie lokalnej (WordPress 7.1.3, PHP 8.2 z Local, WP-CLI z Local):

- `php -l` — 32 pliki PHP pluginu i motywu bez błędów; `node --check` — JS bez błędów; `sass` — oba bundle kompilują się; `gulp build` — OK;
- **testy integracyjne przez WP-CLI — 63/63 OK** (ostatnie uruchomienie po zmianach popołudniowych; dodatkowo: daty wydarzeń i migracja flagi mapy, zapis edycji w panelu, `[maraton_cta]`, przycisk „Sprawdź wszystkie”, link „zobacz wszystkie”, wyszukiwarka Salesforce Topic na symulowanych odpowiedziach z kontrolą uprawnień i escapowania SOQL, usunięcie InPost; smoke test HTTP `/petycja/` — 3 karty akcji, `/wydarzenie/` — 8 wydarzeń, sekcja materiałów na petycji z tymczasowym plikiem testowym). Każdy zapis w transakcji wycofywanej po teście; ruch do Salesforce i wysyłka e-maili przechwycone (2 próby połączeń zablokowane); po testach w bazie brak danych testowych. Zakres:
  - zapis i sanityzacja dat edycji, brak pól blokujących zapis formularza ustawień;
  - licznik wydarzeń i licznik listów zgodne z bezpośrednim zapytaniem SQL (dla zakresu 2026-01-01–2026-09-30: 7 wydarzeń, 734 listy z 3 petycji); pusty zakres = 0;
  - „Podpisz wszystkie” podpisuje bieżącą petycję + petycje z zakresu, nie wszystkie opublikowane;
  - rejestracja → e-mail aktywacyjny → blokada logowania → zły klucz odrzucony → aktywacja → logowanie; walidacja (krótkie hasło, brak zgody, zajęty e-mail); zgody z datą; zgoda marketingowa opcjonalna; link resetu hasła; brak pól loginu i powtórzenia e-maila;
  - metabox `material_order_details` zarejestrowany; widget `block_counter` rejestrowany z pluginu;
  - log pluginu zapisywany w `_wpeprivate/`;
- smoke test HTTP: strona główna, archiwum `/petycja/`, pojedyncza petycja (formularz, „Podpisz wszystkie” zaznaczone), `/zaloguj-sie/` (także z `?registered=1`, `?activated=1`), `/wydarzenie/`, `/kup-znaczek/` — 200, bez błędów PHP w HTML i bez nowych wpisów w `debug.log`; `/panel/` → 302 dla niezalogowanych;
- na stronie głównej licznik wydarzeń pokazuje 1000 (ustawienie widgetu w Elementorze), a rzeczywista liczba wydarzeń 2026 to 8 — do poprawienia w treści.

Nie wykonano:

- testów w przeglądarce (kliknięcia, wygląd, mobile) i wysyłki prawdziwych e-maili;
- zapisu formularza ustawień przez przeglądarkę (sprawdzono wygenerowany HTML i zapis opcji po stronie serwera);
- testów automatycznych w repozytorium i CI — nie istnieją (skrypt testów integracyjnych jest poza repozytorium).

### Naprawiony błąd: zapis ustawień pluginu (2026-10-14)

Formularz Petitions → Ustawienia nie zapisywał się wcale (dotyczy wszystkich zakładek, nie tylko dat edycji). Pole „Podziękowanie: link więcej działań” (`type="url"`) dostawało domyślną wartość `#`, którą przeglądarka uznaje za nieprawidłowy URL; pole jest w ukrytej zakładce, więc przeglądarka blokowała wysłanie bez widocznego komunikatu. Opcje `amnesty_thankyou_*` nie były nigdy zapisane w bazie. Poprawka w `includes/settings.php`: pola URL w formularzu mają domyślnie pustą wartość (frontend nadal używa `#` jako awaryjnego linku), a przy błędzie walidacji formularz przełącza się na zakładkę z błędnym polem.

Kontrole z 2026-10-01 (z poprzedniej wersji tego dokumentu, na wcześniejszym stanie kodu): podpis testowy z synchronizacją do Salesforce Sandbox, logowanie i przekierowanie do `/panel/`, pobranie PDF raportu, widok petycji na desktopie i mobile.

## Decyzje

| Data | Decyzja | Konsekwencja w kodzie |
|---|---|---|
| 2026-10-14 | InPost wycofany na rzecz DPD | HEAD nadal ma InPost w formularzu zamówień i moduł `events-shipping-api.php`; zmiany robocze przełączają formularz na DPD |
| 2026-10-14 | `material_order` (zamówienia materiałów na wydarzenie) pozostaje potrzebny | metabox i statusy zamówień muszą działać — patrz ryzyko niżej |
| 2026-10-14 | „Widoczne na mapie” to osobna flaga, niezależna od „wydarzenie publiczne” | wprowadzana w zmianach roboczych (`widoczne_na_mapie`) |
| 2026-10-14 | Produkcja powstanie ze stagingu WP Engine | proces nieopisany — patrz otwarte kwestie |
| 2026-10-14 | Zakres dat licznika wydarzeń ustawiany globalnie | nie zaimplementowano — [requirements.md](requirements.md#decyzje-z-2026-10-14) |
| 2026-10-14 | „Podpisz wszystkie” tylko dla tegorocznych petycji; „tegoroczne” = w globalnym zakresie dat edycji z kokpitu (wspólnym z licznikiem wydarzeń i licznikiem listów) | obecnie podpisuje wszystkie opublikowane, a licznik listów liczy rok kalendarzowy — do zmiany |

## Zmiany robocze (niezatwierdzone)

Stan `git status` z 2026-10-14 (bez `docs/`): 22 zmienione pliki, 1 usunięty, 1 usunięty z indeksu (`amnesty-debug.log`, zmiana w stage), 3 nowe nieśledzone. Nie są opisane w dokumentach modułów.

| Obszar | Zmiana |
|---|---|
| Widget licznika | klasa `Block_Counter_Widget` przeniesiona do pluginu (`includes/widgets/class-petition-block-counter.php`, nowy `assets/js/amnesty-counter.js`); `functions/widgets/block-counter.php` motywu usunięty, kopia jako nieśledzony `block-counterold.php` |
| Plugin | nowy shortcode `[letters_counter]` (suma listów z petycji edycji), filtr `body_class` usuwający klasę kitu Elementora, `wpautop` dla krótkiego opisu, teksty domyślne przez `esc_html__`, zmiany CSS/JS formularza |
| Zakres edycji (2026-10-14) | nowy moduł `includes/edition.php`; zakładka „Edycja” w ustawieniach pluginu (`amnesty_edition_start`, `amnesty_edition_end`); zakres używany przez licznik wydarzeń, licznik listów i „Podpisz wszystkie”; bez dat = bieżący rok kalendarzowy; usunięto liczby zastępcze 15/500 w licznikach |
| Rejestracja (przebudowana 2026-10-14) | formularz: e-mail, hasło (min. 8 znaków), wymagana zgoda `consent_marathon`, opcjonalna `consent_amnesty`, daty zgód `consent_*_date`; login generowany z e-maila; aktywacja linkiem z e-maila (`maraton_account_pending`, `maraton_activation_key` — hash), blokada logowania nieaktywnych kont; link „Nie pamiętasz hasła?” do `wp_lostpassword_url()`; etykieta logowania „Adres e-mail” |
| Menu | dynamiczna pozycja „Panel” z właściwością `current` |
| Wydarzenia | checkbox `show_on_map` → meta `widoczne_na_mapie`; mapa pomija wydarzenia bez `widoczne_na_mapie = '1'`; przekierowanie po dodaniu do kotwicy `#zakladka-dodaj-wydarzenie` |
| Materiały | przewoźnicy `Kurier DPD` / `DPD Pickup`, adres dostawy (`adres_ulica`, `adres_kod`, `adres_miasto`), nowy shortcode `[digital_materials]`; panel `[my_events]` pokazuje zamówienia |
| Motyw | zmiany SCSS i przebudowane `dist/front.min.css`, `dist/para.min.js` |
| Porządek SCSS (2026-10-14) | `source/scss/` podzielone na `base/`, `layout/`, `components/`, `events/`, `widgets/`, `admin/` (mapa folderów w nagłówku `front.scss`); `components/events.scss` i `default.scss` rozbite na pliki tematyczne; style ekranów wp-admin przeniesione z bundla frontu do `admin.scss`; usunięte nieużywane reguły InPost (`.btn-inpost`, `.inpost-selection-status`, `.inpost-map-container`) i `.amnesty-pin-private`. Skompilowany CSS porównano regułę po regule z wersją sprzed zmian: poza wymienionymi różnicami identyczny, jedyna zmiana kolejności to style `.digital-materials-*` (unikalne selektory, bez wpływu na kaskadę). `dist/` przebudowane (`gulp build`) |
| Funkcje dodane 2026-10-14 (po południu) | lista akcji `/petycja/` (szablon archiwum w pluginie, petycje bieżącej edycji) i link „zobacz wszystkie” przy „Podpisz wszystkie”; sekcja materiałów do pobrania na stronie akcji; lista wydarzeń `/wydarzenie/` (`archive-wydarzenie.php`, `functions/events-list.php`) i przycisk „Sprawdź wszystkie” w `[events_map]`; `[maraton_cta]`; checkbox „widoczne na mapie” w edycji w panelu; daty wydarzeń w obu formatach + `maraton_get_event_datetime()`; migracja flagi mapy; podpowiedzi Salesforce Topic w kokpicie (`amnesty_sf_get_access_token()` wydzielone z wysyłki podpisu); pole tekstu zgody „Podpisz wszystkie”; poprawki: ostrzeżenie `$show_map`, pusty napis „+ Dodaj materiał”, brakujący obrazek zastępczy karty; usunięte: moduł InPost, `block-counterold.php`, `console.log` |
| Log pluginu (2026-10-14) | `amnesty_custom_log()` zapisuje do `_wpeprivate/amnesty-debug.log` (stała `AMNESTY_PETITIONS_LOG_DIR`, fallback `error_log`); plik usunięty z indeksu git, reguła `**/amnesty-debug.log` w `.gitignore`; dotychczasowa treść logu przeniesiona lokalnie do `app/public/_wpeprivate/` — szczegóły w [deployment.md](deployment.md#log-pluginu) |

### Ryzyka zmian roboczych

1. ~~Zależność motywu od pluginu~~ — **rozwiązane 2026-10-14:** `Block_Counter_Widget` rejestruje plugin (`includes/elementor-widgets.php`); motyw nie odwołuje się już do tej klasy. Nieśledzony `functions/widgets/block-counterold.php` w motywie pozostał (nie jest ładowany).
2. ~~Usunięta rejestracja metaboxa `material_order`~~ — **rozwiązane 2026-10-14:** przywrócono `add_material_order_meta_box()` w postaci z HEAD.
3. ~~Wydarzenia znikające z mapy~~ — **rozwiązane 2026-10-14:** jednorazowa migracja (`maraton_events_migration` = 1) ustawia `widoczne_na_mapie` = 1 wydarzeniom bez tej flagi; uruchamia się przy pierwszym żądaniu po wdrożeniu (lokalnie już wykonana).
4. ~~Moduł InPost~~ — **usunięty 2026-10-14** (`functions/events-shipping-api.php`, `git rm`); opcje `inpost_*` i `shipping_sender_*` zostały w bazie (nieużywane).
5. ~~`console.log` w `amnesty-counter.js`~~ — usunięty 2026-10-14.

Poprawki 1, 2, log, zakres edycji i rejestrację sprawdzono `php -l` oraz testem logiki na atrapach funkcji WordPressa (zakres dat, sanityzacja dat, generowanie loginu) — nie testowano ich w działającym WordPressie (baza lokalna wyłączona). Do przetestowania na stronie: rejestracja → e-mail → aktywacja → logowanie, reset hasła, liczniki po ustawieniu dat edycji, „Podpisz wszystkie”.

## Znane problemy w HEAD

- `[amnesty_petition_map]` odpytuje akcję `get_amnesty_map_data`, której moduł (`ajax-map.php`) nie jest ładowany. Od 2026-10-14 (robocze) szablon akcji pokazuje mapę sygnatariuszy tylko, gdy endpoint jest włączony.
- ~~Różne klucze dat w formularzu dodawania i edycji~~ — rozwiązane 2026-10-14 (robocze): oba zapisują oba formaty, odczyt przez `maraton_get_event_datetime()`.
- Plik `plugins/amnesty-petitions-mk/amnesty-debug.log` z danymi osobowymi (adres e-mail) jest w historii git od commita `f7c0a06` (wypchniętego do `origin`). Usunięcie z bieżącego drzewa czeka na commit; usunięcie z historii wymaga przepisania historii i force-pusha — niewykonane.
- ~~`amnesty_text_consent_sign_all` bez pola w ustawieniach~~ — dodane 2026-10-14 (robocze).
- Sekrety Salesforce i InPost są przechowywane jako opcje WordPressa (tekst jawny w bazie).

## Otwarte kwestie

- Metoda przenoszenia kodu z repozytorium na staging WP Engine — nieopisana; reszta procedury i lista kontrolna: [deployment.md](deployment.md).
- Tabela podpisów powstaje tylko przy aktywacji pluginu — sposoby obejścia i propozycja wersjonowania schematu: [deployment.md](deployment.md#tabela-podpisów-na-nowym-środowisku).
- Czy `dist/` ma być commitowany ręcznie, czy odtwarzany z `source/` w procesie wdrożenia.
- ~~Migracja `widoczne_na_mapie`~~ — zrobiona (patrz ryzyko 3).
- Czy DPD ma mieć integrację API (etykiety), czy obsługę ręczną (moduł InPost usunięty).
- Usunięcie `amnesty-debug.log` z historii git i z GitHuba (repozytorium publiczne) — **Decyzja (2026-10-14):** właściciel projektu wykona to ręcznie. Po przepisaniu historii hashe commitów podane w `docs/` będą nieaktualne.
- Potwierdzenie na stagingu, że `_wpeprivate/` nie jest dostępny z sieci.
- Generator PDF raportu jest wstępny (jedna strona, 43 linie, bez polskich znaków).

Kopie `wp-content/wp-content/` i `wp-content/wp-content-1/` (stare szkielety motywu, nieśledzone przez git) usunięto 2026-10-14 za zgodą właściciela projektu.
