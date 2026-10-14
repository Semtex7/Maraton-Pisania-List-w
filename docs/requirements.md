# Wymagania i stan realizacji

Stan: 2026-10-14. Kod: `develop` @ `02ddaac` + zmiany robocze ([status.md](status.md#zmiany-robocze-niezatwierdzone)). Treść stron: zrzut bazy lokalnej `app/sql/local.sql` z 2026-10-08 (poza repozytorium) — późniejsze zmiany w Elementorze nie są uwzględnione.

## Źródła wymagań

| Skrót | Źródło |
|---|---|
| **B** | Brief „System Maratonu Pisania Listów – maraton.amnesty.org.pl” (z projektem w Canvie — projektu nie weryfikowano) |
| **N** | Notatka ze spotkania 2026-10-02 |
| **K** | Wiadomość od przełożonego (Krzysztof) |
| **A** | Opis „Archiwum Petycji” |

## Statusy

- **Zrobione** — działa w kodzie i jest użyte w treści;
- **Częściowo** — kod lub treść niekompletne; opis w kolumnie „Brakuje”;
- **Brak** — nie ma implementacji;
- **Treść** — mechanizm istnieje, trzeba uzupełnić lub poprawić treść w Elementorze/kokpicie;
- **Do weryfikacji** — nie da się potwierdzić z kodu ani zrzutu (wymaga przeglądarki lub projektu Canva).

Pozycje oznaczone „(robocze)” istnieją tylko w niezatwierdzonych zmianach.

## Stałe elementy (B)

| Wymaganie | Status | Stan / dowód | Brakuje |
|---|---|---|---|
| Header: hero ze zdjęciem zmienianym w kokpicie | Do weryfikacji | szablon nagłówka Elementor (ID 39) zawiera tylko logo i menu | ustalić, gdzie ma być hero (szablon nagłówka czy sekcja stron) |
| Header: logo Maratonu z lewej, menu wg projektu | Zrobione | szablon nagłówka: `theme-site-logo`, `nav-menu` | zgodność z Canvą — do weryfikacji |
| Footer: logo AI z claimem, edytowalne menu, © 2026 | Brak | szablon stopki (ID 313) zawiera demonstracyjne treści („Products”, „Features”, „Get Started”, „About”) | zbudować stopkę wg projektu |

## Strona główna (B, N)

Strona główna = strona ID 15 „Maraton Pisania Listów”.

| Wymaganie | Status | Stan / dowód | Brakuje |
|---|---|---|---|
| S1: nagłówek + opis | Zrobione | treść strony | — |
| S1: licznik wydarzeń | Częściowo | (robocze) widget `block_counter` ze źródłem „Pobierz liczbę wydarzeń” liczy opublikowane `wydarzenie` utworzone w zakresie edycji ([`edition.php`](../plugins/amnesty-petitions-mk/includes/edition.php)); usunięto liczby zastępcze 15/500. Na stronie głównej widget nadal ma ustawienia ze starszej wersji (`use_dynamic_count`, `target_number` = 1000) i pokaże stałe 1000 | **Treść:** w Elementorze ustawić „Źródło danych” = „Pobierz liczbę wydarzeń” |
| S1: zakres dat liczonych wydarzeń (np. 01.09.2026–15.02.2027) | Zrobione (robocze) | globalne ustawienie: Petitions → Ustawienia → zakładka „Edycja” (`amnesty_edition_start`, `amnesty_edition_end`, włącznie); bez dat = bieżący rok kalendarzowy | ustawić daty edycji w kokpicie |
| S1: przycisk rejestracja (niezalogowany) / dodaj wydarzenie (zalogowany) | Zrobione (robocze) / Treść | shortcode `[maraton_cta]`: gość → `/zaloguj-sie/`, zalogowany → `/panel/#zakladka-dodaj-wydarzenie`; etykiety i adresy w atrybutach ([`events-auth.php`](../themes/hello-elementor-child/functions/events-auth.php)) | **Treść:** zastąpić przycisk na stronie głównej widgetem Shortcode z `[maraton_cta]` |
| S2: miniatury akcji — wybór akcji, automatycznie licznik, kraj, link | Zrobione | widget `amnesty_petition_card_widget` (wybór petycji z listy) | — |
| S2: zdjęcie i tytuł uzupełniane ręcznie | Częściowo | zdjęcie i tytuł są pobierane z petycji (obrazek wyróżniający, tytuł wpisu) | decyzja: czy wystarczy, czy dodać pola nadpisania w widgecie |
| S2: max 3 w linii, wyśrodkowane; mobile 1 | Treść / Do weryfikacji | na stronie jest 5 kart, wszystkie z tą samą petycją (ID 245) | ustawić właściwe akcje; sprawdzić układ w przeglądarce |
| S3: wyszukiwarka wydarzeń | Zrobione | `#map-search-bar` w `[events_map]` filtruje po tytule i adresie ([`para.js`](../themes/hello-elementor-child/source/js/para.js)) | — |
| S3: mapa wydarzeń | Zrobione (robocze) | flaga `widoczne_na_mapie` ustawiana przy dodawaniu i w edycji w panelu; publiczne na zielono (`#84cc16`), niepubliczne na czerwono (`#dc0032`); jednorazowa migracja ustawia flagę = 1 wydarzeniom, które jej nie miały (wcześniej były na mapie) — `maraton_run_event_migrations()` w [`events-core.php`](../themes/hello-elementor-child/functions/events-core.php) | — |
| S3: przycisk „Sprawdź wszystkie” → lista wydarzeń | Zrobione (robocze) | `[events_map]` dodaje pod mapą przycisk do `/wydarzenie/` (wyłączenie: `show_all="0"`); lista wydarzeń: szablon [`archive-wydarzenie.php`](../themes/hello-elementor-child/archive-wydarzenie.php) — te same wydarzenia co na mapie, sortowane po dacie, z wyszukiwarką i oznaczeniem otwarte/zamknięte/dostępność | — |
| S4: tekst + „Dowiedz się więcej” → „Jak zorganizować Maraton?” | Treść | przycisk bez linku | link do strony `jak-zorganizowac-maraton` (strona istnieje, bez treści Elementora) |
| S4: film YouTube `9pRX5RGabvU` | Treść | osadzony inny film (`XHOmBV4js_E`) | podmienić URL |
| S5: slider (tytuł, opis, przycisk, zdjęcie) edytowalny w kokpicie (B, N) | Brak | jeden statyczny slajd; przycisk „Więcej” bez linku | slider (np. widget Slides z Elementor Pro albo własny) |
| S6: Wesprzyj Maraton! + przycisk do „Kup znaczek” | Treść | przycisk „Dowiedz się więcej” bez linku | tekst i link do strony `kup-znaczek` |
| S7: patronat medialny z linkowanymi grafikami | Do weryfikacji | 3 obrazki; linków nie sprawdzono | — |
| Licznik listów ze wszystkich tegorocznych petycji (N) | Częściowo | (robocze) `[letters_counter]` i źródło „Pobierz liczbę podpisów” liczą petycje opublikowane w zakresie edycji (podpisy z tabeli + listy offline); usunięto liczbę zastępczą 500 | **Treść:** wstawić licznik na stronę główną |

## Moduł akcji (B, K)

| Wymaganie | Status | Stan / dowód | Brakuje |
|---|---|---|---|
| Własny szablon strony akcji | Zrobione | [`single-amnesty_petition.php`](../plugins/amnesty-petitions-mk/templates/single-amnesty_petition.php) | — |
| S1: zdjęcie, bohater, kraj, opis, przycisk z popupem YouTube | Zrobione | modal `#amnesty-video-modal` | — |
| S3: materiały dodatkowe do pobrania (PDF, PPTX…) z tytułami | Zrobione (robocze) | sekcja „Pobierz materiały dodatkowe” w szablonie akcji: tytuł + rozszerzenie pliku, link do pobrania | — |
| Formularz: List do władz / List solidarnościowy, domyślnie do władz | Zrobione | `letter_type` = `appeal` domyślnie | — |
| Textarea z domyślną, edytowalną treścią | Zrobione | treść z meta petycji | — |
| Pola: Imię*, Nazwisko*, E-mail*, Telefon, Miejscowość | Zrobione | [`shortcodes.php`](../plugins/amnesty-petitions-mk/includes/shortcodes.php), walidacja w [`ajax-salesforce.php`](../plugins/amnesty-petitions-mk/includes/ajax-salesforce.php) | — |
| Zgoda wymagana + zgoda marketingowa | Zrobione | `rodo_consent` (required), `campaign_consent`; treści z ustawień pluginu | sprawdzić treści w ustawieniach względem briefu |
| „Podpisz wszystkie” domyślnie zaznaczone (B, N) | Zrobione (robocze) | `sign_all` z `checked`; podpisuje bieżącą petycję + petycje opublikowane w zakresie edycji ([`ajax-salesforce.php`](../plugins/amnesty-petitions-mk/includes/ajax-salesforce.php)) | — |
| Link „zobacz wszystkie” przy „Podpisz wszystkie”, dodawany jak link do polityki prywatności (B, K) | Zrobione (robocze) | link dopisywany do tekstu zgody; adres w Ustawienia → Linki (`amnesty_link_all_petitions`), domyślnie lista akcji `/petycja/` — szablon [`archive-amnesty_petition.php`](../plugins/amnesty-petitions-mk/templates/archive-amnesty_petition.php) z kartami petycji bieżącej edycji; tekst zgody edytowalny w Ustawienia → Treści | — |
| Klauzula RODO mniejszą czcionką | Do weryfikacji | teksty z opcji `amnesty_text_rodo_*` | porównać treść z briefem |
| Thank you `#thankyou`: tekst, liczba listów, 3 przyciski (wydarzenia, Kup znaczek, amnesty.org.pl) | Zrobione | etykiety i linki w ustawieniach pluginu | sprawdzić ustawione linki |
| Kokpit: pola akcji (kraj, ID, opis, skrócony opis, wideo, treści listów, materiały z tytułem, dodatkowa i docelowa liczba listów) | Zrobione | metabox w [`cpt-meta.php`](../plugins/amnesty-petitions-mk/includes/cpt-meta.php) | czy „Imię i nazwisko” bohatera ma być osobnym polem (obecnie: tytuł wpisu + adresat) — do weryfikacji |
| Kokpit: Salesforce Topic wybierany z listy z wyszukiwarką | Zrobione (robocze) | pole z podpowiedziami: zapytanie SOQL `LIKE` do Salesforce (AJAX `amnesty_sf_search_topics`, `edit_posts`, cache 10 min); wpisanie nazwy spoza listy nadal tworzy nowy Topic | test z prawdziwym Salesforce (sandbox) na stagingu |

## Moduł użytkownika (B, K)

| Wymaganie | Status | Stan / dowód | Brakuje |
|---|---|---|---|
| Rejestracja: e-mail + hasło, layout wg Canvy | Zrobione (robocze) | formularz: e-mail, hasło (min. 8 znaków, „Pokaż hasło”), wymagana zgoda na przetwarzanie danych, opcjonalna zgoda marketingowa; nazwa użytkownika generowana z e-maila; zgody zapisywane z datą (`consent_*_date`) — [`events-auth.php`](../themes/hello-elementor-child/functions/events-auth.php) | layout wg Canvy — do weryfikacji; treści zgód do potwierdzenia przez ochronę danych Amnesty |
| Potwierdzenie rejestracji linkiem e-mail przed pierwszym logowaniem (K) | Zrobione (robocze) | konto z meta `maraton_account_pending` nie może się zalogować (filtr `authenticate`, także `wp-login.php`); link z jednorazowym kluczem (w bazie tylko hash SHA-256) aktywuje konto; próba logowania nieaktywnego konta z poprawnym hasłem wysyła nowy link | test wysyłki maili na stagingu (zależy od konfiguracji poczty WP Engine) |
| Link „Zapomniałem hasła” pod logowaniem (K) | Zrobione (robocze) | link „Nie pamiętasz hasła?” do standardowego resetu WordPressa (`wp_lostpassword_url()`) | reset odbywa się na ekranach `wp-login.php` (wygląd WordPressa, nie Canvy) |

## Panel organizatora (N)

| Wymaganie | Status | Stan / dowód | Brakuje |
|---|---|---|---|
| Zamówienie pakietów: Kurier DPD z adresem, DPD Pickup z punktem | Zrobione (robocze) | kod punktu wpisywany ręcznie po wybraniu na mapie DPD (link do mapy DPD) — wariant dopuszczony w notatce | — |
| Dodawanie wydarzenia: osobno „publiczne” i „widoczne na mapie” | Zrobione (robocze) | [`events-form.php`](../themes/hello-elementor-child/functions/events-form.php) | ten sam podział w edycji w panelu ([`events-panel.php`](../themes/hello-elementor-child/functions/events-panel.php)) |
| Usunięcie „Pobierz zaświadczenie” z panelu | Zrobione (robocze) | link do PDF usunięty z panelu; endpoint `?download_report` nadal działa | — |
| Raport: „Wyślij raport” → formularz → ręczne potwierdzenie przez Amnesty → „Raport wysłany” → „Pobierz zaświadczenie” (PDF z danymi wydarzenia i podpisem) | Częściowo (etap późniejszy wg N) | formularz raportu istnieje; po wysłaniu panel od razu pokazuje „Raport wysłany”; brak potwierdzenia i zaświadczenia | status „oczekuje / potwierdzony” edytowany w adminie; generator zaświadczenia z podpisem (obecny PDF jest wstępny, bez polskich znaków) |
| Zmniejszenie wielkości czcionek | Do weryfikacji | zmiany SCSS w zmianach roboczych; zakres nieopisany | — |

## Do zrobienia później (N)

| Wymaganie | Status | Stan |
|---|---|---|
| Google Tag Manager, Meta Pixel, TikTok Pixel | Brak | brak w kodzie i w zrzucie bazy |
| Cookies (baner zgód) | Brak | brak pluginu ani kodu zgód; skrypty śledzące powinny zależeć od zgody |

## Archiwum petycji (A)

| Wymaganie | Status | Stan |
|---|---|---|
| Parametr „Archiwum” przy petycji | Brak | — |
| Strona petycji: sekcje AKTUALNE (3 pierwsze wyróżnione) i ARCHIWUM, wyszukiwarka i paginacja bez zmian | Brak | opis dotyczy `amnesty.org.pl/petycje/` i szablonu „Petition Index”, którego nie ma w tym repozytorium |
| Petycja archiwalna: komunikat „Liczba zebranych podpisów: X. Dziękujemy za udział w akcji!” zamiast formularza | Brak | — |

**Otwarta kwestia:** czy to wymaganie dotyczy tego projektu (strona maratonu, plugin `amnesty-petitions-mk`), czy głównej strony amnesty.org.pl w innym repozytorium.

## Usterki wykryte przy weryfikacji

- ~~Widget licznika pokazuje liczby zastępcze (15 / 500), gdy wynik wynosi 0~~ — usunięte w zmianach roboczych 2026-10-14.
- W treściach są instancje widgetu `amnesty_petition_map_widget`, który nie jest rejestrowany — nic się w ich miejscu nie wyświetli. Mapa sygnatariuszy w szablonie petycji nie ma działającego endpointu ([amnesty-petitions-mk.md](amnesty-petitions-mk.md#mapa-sygnatariuszy)).
- Repozytorium na GitHubie jest publiczne, a jego historia zawiera log z adresem e-mail ([status.md](status.md#znane-problemy-w-head)).

## Decyzje z 2026-10-14

| Temat | Ustalenie | Status |
|---|---|---|
| Archiwum petycji | prawdopodobnie dotyczy amnesty.org.pl, nie tego projektu | **Otwarte** — właściciel projektu weryfikuje |
| Zakres dat licznika wydarzeń | ustawiany **globalnie** w kokpicie (jedno ustawienie dla edycji) | **Decyzja** |
| „Podpisz wszystkie” | podpisuje **tylko tegoroczne** petycje | **Decyzja** |
| Definicja „tegorocznych” | petycje z globalnego zakresu dat edycji ustawianego w kokpicie (ten sam zakres co dla licznika wydarzeń); dotyczy „Podpisz wszystkie” i licznika listów | **Decyzja** |
| Formularz rejestracji | wg tabeli poniżej | **Decyzja** (zaakceptowane 2026-10-14) |

### Formularz rejestracji

Status: **Decyzja** (zaakceptowane 2026-10-14; zaimplementowane w zmianach roboczych). Treści zgód do potwierdzenia przez osobę odpowiedzialną za ochronę danych w Amnesty.

| Element | Ustalenie | Uzasadnienie |
|---|---|---|
| Nazwa użytkownika | usunąć z formularza; generować automatycznie z e-maila | brief: tylko e-mail i hasło; logowanie e-mailem już działa (`wp_signon` przyjmuje e-mail) |
| E-mail | jedno pole | — |
| Powtórzenie e-maila | usunąć | literówkę wyłapie link aktywacyjny (konto z błędnym e-mailem nie zostanie aktywowane) |
| Hasło | jedno pole z „pokaż hasło”, min. 8 znaków; bez wymogu znaku specjalnego | długość chroni lepiej niż wymuszone znaki; pomyłkę naprawia reset hasła |
| Zgoda na przetwarzanie danych w celu organizacji Maratonu | wymagana | podstawa udziału w Maratonie |
| Zgoda marketingowa Amnesty | **opcjonalna** | zgoda warunkująca założenie konta nie jest dobrowolna (art. 7 ust. 4 RODO) |
| Zapis zgód | wartość + data udzielenia | rozliczalność zgody (art. 7 ust. 1 RODO) |
| Aktywacja | link w e-mailu; logowanie dopiero po kliknięciu (wymaganie K) | — |
| Reset hasła | link „Zapomniałem hasła” pod logowaniem (wymaganie K) | — |

## Otwarte pytania do zespołu

1. Archiwum petycji — potwierdzenie, że dotyczy amnesty.org.pl.
2. ~~Data publikacji czy osobne pole „edycja”?~~ — **Decyzja (2026-10-14):** data publikacji petycji / utworzenia wydarzenia.
3. ~~Akceptacja propozycji formularza rejestracji~~ — zaakceptowano 2026-10-14.
4. Miniatury akcji — czy zdjęcie i tytuł mają być nadpisywane ręcznie w widgecie?
5. Header hero — w szablonie nagłówka (wspólny) czy jako sekcja na poszczególnych stronach?
