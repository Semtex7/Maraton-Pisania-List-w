# Motyw hello-elementor-child

Stan: 2026-10-14, `develop` @ `02ddaac`. Zmiany robocze: [status.md](status.md#zmiany-robocze-niezatwierdzone).

## Rola

Motyw potomny Hello Elementor. Poza wyglądem strony zawiera logikę dla organizatorów wydarzeń Maratonu: konta, wydarzenia, mapę, panel, zamówienia materiałów, raporty i (wycofaną) wysyłkę InPost.

## Ładowanie modułów

[`functions.php`](../themes/hello-elementor-child/functions.php) dołącza przez `require_once` moduły z [`functions/`](../themes/hello-elementor-child/functions/); brak pliku jest logowany przez `error_log`.

| Moduł | Odpowiedzialność |
|---|---|
| `enqueue-scripts.php` | zasoby frontu z `dist/` i `style.css`; style poprawek edytora Elementora |
| `custom-functions.php` | przekierowanie niezalogowanych ze strony `panel` |
| `events-core.php` | CPT `wydarzenie`, blokada `wp-admin` dla subskrybentów, ukrycie admin bara |
| `events-auth.php` | `[event_login]`, `[event_registration]`, dynamiczne pozycje menu |
| `events-form.php` | `[event_form]` — dodawanie wydarzenia |
| `events-map.php` | `[events_map]` i AJAX `get_events_map_data` |
| `elementor-widgets-manager.php` | rejestracja widgetu `Block_Counter_Widget` z `functions/widgets/block-counter.php` (plik w HEAD; w drzewie roboczym usunięty) |
| `events-panel.php` | `[my_events]` i AJAX edycji wydarzenia |
| `events-materials.php` | CPT `material_order`, `[materials_order]`, strona materiałów do pobrania |
| `events-reports.php` | raport po wydarzeniu, PDF, metabox raportu |
| `events-shipping-api.php` | ustawienia i etykiety InPost — **wycofane**, patrz niżej |
| `shortcodes.php` | pusty plik |

## Konta i dostęp

- `[event_login]` — POST obsługiwany w `template_redirect` (priorytet 1) przez `wp_signon()`, nonce `event_login_nonce`. Błąd zostaje na stronie formularza; sukces → `home_url('/panel/')`.
- `[event_registration]` — pola: login, e-mail, hasło; `wp_create_user()`, automatyczne zalogowanie (`wp_set_auth_cookie`) i przekierowanie do `/panel/`. Rola nowego konta wynika z domyślnej roli WordPressa (kod jej nie ustawia).
- [`custom-functions.php`](../themes/hello-elementor-child/functions/custom-functions.php): niezalogowany na stronie `panel` → `wp_login_url()` (standardowy login WP, nie `[event_login]`; w zmianach roboczych z 2026-10-14 → `/zaloguj-sie/`). Pominięte w podglądzie Elementora.
- [`events-core.php`](../themes/hello-elementor-child/functions/events-core.php): użytkownik z pierwszą rolą `subscriber` wchodzący do `wp-admin` (poza AJAX) → `/panel/`. Admin bar ukryty dla osób bez `manage_options`.
- Filtr `wp_nav_menu_objects` dopasowuje pozycje menu **po tytule** („Zaloguj się”, „Zarejestruj się”, „Panel”) i ukrywa/podmienia je zależnie od zalogowania. Zmiana tytułu w Wygląd → Menu psuje to zachowanie.

## Wydarzenia

CPT `wydarzenie` (menu „Wydarzenia na mapie”), publiczny. Właścicielem wydarzenia jest autor wpisu (`post_author`) — na tym opierają się kontrole uprawnień w panelu i raportach.

**Dodawanie** — [`events-form.php`](../themes/hello-elementor-child/functions/events-form.php), nonce `save_event_action`:

- adres jest geokodowany przez Nominatim; brak wyniku = błąd formularza, wpis nie powstaje;
- wpis tworzony od razu ze statusem `publish`;
- pola zapisywane przez `update_field()` (ACF), a bez ACF przez `update_post_meta()`: `data_rozpoczecia`, `godzina_rozpoczecia`, `data_zakonczenia`, `godzina_zakonczenia`, `nazwa_lokalu`, `ulica`, `miejscowosc`, `kod_pocztowy`, `wydarzenie_publiczne`, `dostepnosc`, `geo_lat`, `geo_lng`;
- po zapisie przekierowanie z `?added=1` (ochrona przed ponownym wysłaniem).

**Edycja w panelu** — [`events-panel.php`](../themes/hello-elementor-child/functions/events-panel.php), AJAX `get_event_form` / `save_event_form`, nonce `ajax_edit_action`, tylko autor. Edycja używa pól `data_czas_rozpoczecia` i `data_czas_zakonczenia` (datetime), a nie par data/godzina z formularza dodawania.

**Otwarta kwestia:** formularz dodawania i edycja w panelu używają różnych kluczy dat. Raport zależy od `data_czas_zakonczenia`, więc wydarzenie, którego nie edytowano w panelu, może nie mieć daty wymaganej do raportu. Nie zweryfikowano w działającej stronie ani w konfiguracji pól ACF.

## Mapa wydarzeń

[`events-map.php`](../themes/hello-elementor-child/functions/events-map.php):

- `[events_map]` ładuje Leaflet 1.9.4 z `unpkg.com`;
- AJAX `get_events_map_data` zwraca wszystkie opublikowane wydarzenia ze współrzędnymi (`geo_lat`, `geo_lng`, awaryjnie literówkowy klucz `geo_ing`) wraz z flagą `is_public`;
- wynik jest cache'owany w transiencie `maraton_events_map_data` na 1 godzinę i czyszczony przy `save_post_wydarzenie`.

**Decyzja (2026-10-14):** widoczność na mapie ma być niezależną flagą („widoczne na mapie”), odrębną od „wydarzenie publiczne”. W HEAD flaga nie istnieje — jest wprowadzana w zmianach roboczych.

## Panel organizatora

`[my_events]` wyświetla wydarzenia bieżącego użytkownika (`author`) z akcjami:

- **Edytuj** (AJAX, patrz wyżej);
- **raport** — dostępny po 7 dniach od `data_czas_zakonczenia`; po złożeniu (`raport_zlozony = '1'`) link `?download_report=<id>` do PDF.

W HEAD panel nie pokazuje zamówień materiałów (dodają to zmiany robocze).

## Zamówienia materiałów

[`events-materials.php`](../themes/hello-elementor-child/functions/events-materials.php):

- CPT `material_order` (menu „Zamówienia”), niepubliczny, widoczny w adminie;
- `[materials_order]` — tylko dla zalogowanych; nonce `save_order_action`; zapisuje meta: `powiazane_wydarzenie_id`, `rozmiar_paczki`, `przewoznik`, `punkt_odbioru_id`, `osoba_kontaktowa`, `telefon`, `email`;
- w HEAD przewoźnik to `InPost` lub `DPD` (z identyfikatorem punktu);
- handler nie sprawdza po stronie serwera, czy `event_id` należy do zamawiającego (lista wyboru pokazuje tylko jego wydarzenia);
- metabox „szczegóły zamówienia i status” w adminie; statusy `status_zamowienia`: `oczekujace`, `w_realizacji`, `wyslane`, `anulowane` (zapis z nonce `save_order_status`, wymaga `edit_post`);
- kolumny listy: przewoźnik, punkt odbioru, status;
- strona „Materiały do pobrania” (menu Wydarzenia, `manage_options`) z trzema slotami `global_mat_{1..3}_title/url`.

**Decyzja (2026-10-14):** InPost zostaje wycofany na rzecz DPD. **Decyzja:** `material_order` (zamówienie materiałów na wydarzenie) pozostaje potrzebny.

## Wysyłka InPost (wycofana)

`functions/events-shipping-api.php` jest nadal ładowany w HEAD (w zmianach roboczych z 2026-10-14 moduł usunięto — [status.md](status.md#ryzyka-zmian-roboczych)):

- strona „Ustawienia API” pod menu Zamówienia (opcje `inpost_*`, `shipping_sender_*`);
- AJAX `generate_inpost_label` tworzy przesyłkę w ShipX, zapisuje `inpost_shipment_id`, `inpost_tracking_number` i ustawia status `w_realizacji`;
- `admin-post` `download_inpost_label` pobiera etykietę.

Oba endpointy wymagają `manage_options`. **Otwarta kwestia:** usunięcie lub wyłączenie modułu i odpowiednik dla DPD nie są zaplanowane w kodzie.

## Raport wydarzenia i PDF

[`events-reports.php`](../themes/hello-elementor-child/functions/events-reports.php):

- AJAX `get_report_form` / `save_event_report`, nonce `save_report_action`, tylko autor wydarzenia;
- meta: `raport_zlozony`, `tresc_raportu`, `liczba_podpisanych_listow`, `liczba_uczestnikow`, `materialy_pobrane`, `materialy_uwagi`, `link_do_zdjec`, `plik_zip_url`;
- metabox z podglądem raportu w edycji wydarzenia;
- PDF (`?download_report=<id>`): sprawdza zalogowanie, typ wpisu, autora i złożenie raportu.

Generator `amnesty_build_basic_pdf()` jest wersją wstępną bez biblioteki: jedna strona, font Helvetica/WinAnsi, polskie znaki usuwane przez `remove_accents()`, maksymalnie 43 linie. **Propozycja:** zastąpić go biblioteką PDF z obsługą fontów i wielu stron.

## Widget Elementora

`Block_Counter_Widget` (nazwa `block_counter`) — animowany licznik (wrapper `.custom-block-counter-wrapper` z atrybutami `data-target` i `data-duration`). W HEAD klasa jest w motywie; zmiany robocze przenoszą ją do pluginu ([status.md](status.md#zmiany-robocze-niezatwierdzone)).

## Zasoby i build

[`enqueue-scripts.php`](../themes/hello-elementor-child/functions/enqueue-scripts.php) ładuje na froncie (priorytet 999):

- `dist/front.min.css`, potem `style.css` (oba z wersją `MARATON_VERSION`);
- `dist/para.min.js` z zależnością `jquery` i obiektem `kkAjax.ajaxurl`;
- `assets/css/elementor-editor-fixes.css` tylko w podglądzie Elementora.

Funkcja ładująca zasoby admina (`dist/admin.min.css`, `dist/paraadmin.min.js`) istnieje, ale jej `add_action` jest zakomentowany. Pliki `assets/front.min.css` i `assets/admin.min.css` nie są nigdzie ładowane (pozostałość).

Build — [`package.json`](../themes/hello-elementor-child/package.json), [`gulpfile.js`](../themes/hello-elementor-child/gulpfile.js):

- `npm start` = `gulp` (`default`): `build` + BrowserSync (proxy `maraton-pisania-listow.local`) + `watch`;
- `build`: `sass-front`, `sass-admin` (z `source/scss/`), `scripts-front`, `scripts-admin` (z `source/js/`) → `dist/`;
- osobnego skryptu npm do jednorazowego buildu nie ma (zadanie `build` istnieje w Gulpie);
- większość zależności ma wersję `*`, więc wynik zależy od `package-lock.json`.

Przy zmianach CSS trzeba rozróżnić ręczny [`style.css`](../themes/hello-elementor-child/style.css) od SCSS i wygenerowanego `dist/`. `MARATON_VERSION` (`functions.php`) jest wersją cache-bustingu zasobów — bez jej podbicia przeglądarki mogą trzymać stare pliki.
