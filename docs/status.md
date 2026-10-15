# Stan projektu, decyzje i otwarte kwestie

Stan na: 2026-10-14  
Gałąź: `develop` (śledzi `origin/develop`)  
HEAD: `39eafd7` — „changes” (2026-10-14 12:52)  

## Etap projektu

Strona działa lokalnie (Local) i na stagingu (WP Engine). Produkcja nie istnieje — ma powstać ze stagingu na WP Engine w tygodniu od 2026-10-19 (**Decyzja**, właściciel projektu, 2026-10-14).

Commit `39eafd7` zawiera zmiany do ok. 12:50 (2026-10-14). Późniejsze zmiany (poprawki po teście na stagingu, przekierowanie `/panel/`, nadawca maili, synchronizacja Salesforce w tle, testy w `tests/`) są w drzewie roboczym — wymagają commita i wdrożenia. Dokumenty modułów (`architecture.md`, `hello-elementor-child.md`, `amnesty-petitions-mk.md`) opisują stan `02ddaac`; nowsze zmiany są wypisane w tym dokumencie. Lista zadań do startu: [requirements.md](requirements.md).

## Kontrole z 2026-10-14

Wykonane na drzewie roboczym, na działającej stronie lokalnej (WordPress 7.1.3, PHP 8.2 z Local, WP-CLI z Local):

- `php -l` — 32 pliki PHP pluginu i motywu bez błędów; `node --check` — JS bez błędów; `sass` — oba bundle kompilują się; `gulp build` — OK;
- **testy w repozytorium ([tests/README.md](../tests/README.md)), ostatnie uruchomienie: integracyjne 102/102 OK, UI w Chrome 10/10 OK.** Wcześniejszy opis:
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

- testów na urządzeniach mobilnych i wysyłki prawdziwych e-maili (logowanie, panel i formularze sprawdzone w Chrome desktop);
- zapisu formularza ustawień przez przeglądarkę (sprawdzono wygenerowany HTML i zapis opcji po stronie serwera);
- CI — testy są w `tests/`, uruchamiane ręcznie (WP-CLI, npm).

## Obciążenie

Pytanie: czy strona wytrzyma ok. 1000 odwiedzających dziennie. Pomiary: [tests/README.md](../tests/README.md#wyniki-z-2026-10-14-lokalnie-local-na-windows-bez-cache-stron).

- **Strony:** 1000 osób/dzień × kilka odsłon to średnio poniżej 1 odsłony na sekundę. Lokalny serwer (Windows, bez cache) obsłużył ~5 stron/s przy 20 równoczesnych połączeniach bez błędów; na WP Engine strony dla niezalogowanych są dodatkowo serwowane z cache. **Fakt:** pomiar lokalny. **Wniosek (niezweryfikowany na WP Engine):** strony nie są wąskim gardłem.
- **Podpisy — znaleziony problem, naprawiony w zmianach roboczych:** synchronizacja z Salesforce odbywała się w trakcie wysyłania formularza, po kolei dla każdej podpisywanej petycji (4–5 wywołań API na petycję). Przy „Podpisz wszystkie” i 3 petycjach podpis trwał 3,9 s, przy 10 równoczesnych osobach 12–20 s; przy ~10 bohaterach Maratonu byłoby to ponad 12 s na podpis nawet bez tłoku, a w szczycie (np. po newsletterze) — kolejki i przekroczenia czasu. Teraz podpis zapisuje się od razu (0,17 s), a Salesforce jest synchronizowany w tle ([`salesforce-sync.php`](../plugins/amnesty-petitions-mk/includes/salesforce-sync.php)); 50 równoczesnych podpisów: mediana 2,1 s, 0 błędów.
- **Przepustowość kolejki:** ~2,3 podpisu/s przy symulowanych 300 ms na wywołanie API (przebieg z limitem 40 s, zaraz po podpisie i co 5 minut). Przy bardzo dużej fali (tysiące „Podpisz wszystkie” w godzinę) Salesforce może być zasilany z opóźnieniem rzędu godzin — dane nie giną, czekają w kolejce. **Propozycja** na później: Salesforce Composite/Bulk API.
- **Wymaganie wdrożeniowe:** WP-Cron musi działać na WP Engine — [deployment.md](deployment.md#synchronizacja-salesforce-w-tle).

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
| Ochrona formularzy, RODO, zgoda w Salesforce (2026-10-14) | **Ochrona przed botami** (bez usług zewnętrznych): pole-pułapka i minimalny czas na stronie (2 s, mierzony w przeglądarce) w petycji i rejestracji; limit na IP: 60 podpisów / 10 min (łagodny — wspólne IP szkół na wydarzeniach), 5 rejestracji / h; blokada logowania po 10 błędnych próbach / 15 min na IP i na konto (także `wp-login.php`, XML-RPC); boty dostają odpowiedź „sukces” bez zapisu. Wymagana zgoda RODO sprawdzana też po stronie serwera. **Naprawiona luka:** eksport podpisów do CSV nie sprawdzał uprawnień ani nonce — zalogowany subskrybent (każdy może się zarejestrować) mógł pobrać dane osobowe podpisujących; teraz `manage_options` + nonce; CSV ma kolumny zgodne z nagłówkiem. **RODO:** podpisy i zgody organizatorów w Narzędzia → Eksportuj / Usuń dane osobowe (wyszukiwanie po e-mailu; przy usuwaniu komunikat o danych w Salesforce); akcja masowa „Usuń” w Podpisach z potwierdzeniem. **Zgoda marketingowa → Salesforce:** pole Contact z ustawień (`amnesty_sf_consent_field`, domyślnie `Newsletter_Subscribe__c`) ustawiane na `true` przy zgodzie, także dla istniejących kontaktów; nigdy na `false`; błąd pola nie blokuje synchronizacji. Log: adresy e-mail maskowane. Pliki: `includes/form-protection.php`, `includes/privacy.php` (plugin), `functions/form-protection.php` (motyw) |
| Synchronizacja Salesforce w tle + testy (2026-10-14) | podpis nie czeka na Salesforce: kolejka w WP-Cron (zaraz po podpisie i co 5 min), jeden token na przebieg, cache Topic/Contact, ponawianie do 5 prób (`salesforce_attempts`), `DUPLICATE_VALUE` przy przypisaniu = sukces, blokada równoległych przebiegów; status w Podpisach: ✅ / ⏳ / ❌ / —; log bez adresów e-mail (numer podpisu). Schemat tabeli wersjonowany (`amnesty_petitions_db_version` = 2, aktualizacja przy `init`, bez reaktywacji pluginu); stare niezsynchronizowane podpisy nie trafiają do kolejki. Katalog `tests/` (integracyjne, UI, obciążenie) wersjonowany (`.gitignore`) |
| Poprawki po teście na stagingu (2026-10-14) | podpis maila aktywacyjnego „Zespół Amnesty International”, nazwa nadawcy maili „Maraton Pisania Listów – Amnesty International” (adres nadawcy — [deployment.md](deployment.md#wysyłka-e-maili)); odstęp nad przyciskiem formularza wydarzenia i zamówienia (`.form-section-last`, 32 px); odstęp „Nie pamiętasz hasła?” od przycisku logowania (`style.css`, 20 px) + style komunikatów sukcesu rejestracji/aktywacji; zakładki Elementora (Nested Tabs) zawsze na pełną szerokość — usunięty przeskok przy przełączaniu (`components/_tabs.scss`); `MARATON_VERSION` 1.1 → 1.2 (odświeżenie cache CSS/JS motywu); niezalogowany na `/panel/` → `/zaloguj-sie/` zamiast `wp-login.php` (`custom-functions.php`). Sprawdzone w Chrome (headless) na lokalnej stronie: odstęp logowania 3 → 23 px, szerokość paneli zakładek stała (wcześniej 792/950 px), pozycja zakładek stała także w układzie poziomym |
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
