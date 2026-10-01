# Motyw hello-elementor-child

## Rola

`hello-elementor-child` jest aktywnym motywem potomnym rozszerzającym Hello Elementor. Kod własny znajduje się przede wszystkim w [`functions.php`](../themes/hello-elementor-child/functions.php), katalogu [`functions/`](../themes/hello-elementor-child/functions/) oraz w zasobach [`source/`](../themes/hello-elementor-child/source/) i [`dist/`](../themes/hello-elementor-child/dist/).

Motyw korzysta z Elementora i klasy `Maraton\\Maraton_Elementor_Extension`, ale ich kod nie jest częścią tego dokumentu.

## Ładowanie modułów

[`functions.php`](../themes/hello-elementor-child/functions.php) dołącza następujące moduły:

- `enqueue-scripts.php` - style i skrypty frontendowe oraz admina;
- `custom-functions.php` - ograniczenie dostępu do panelu i modyfikacja admin bara;
- `events-core.php` - CPT `wydarzenie` i ustawienia dostępu;
- `events-auth.php` - logowanie, rejestracja, menu i przekierowanie do panelu;
- `events-form.php` - formularz wydarzenia;
- `events-map.php` - frontendowa mapa wydarzeń;
- `elementor-widgets-manager.php` - rozszerzenia widgetów Elementora;
- `events-panel.php` - dane i operacje panelu użytkownika;
- `events-materials.php` - materiały dostępne w panelu;
- `events-reports.php` - raporty;
- `events-shipping-api.php` - integracja wysyłkowa;
- `shortcodes.php` - pozostałe shortcode’y.

Moduły są ładowane przez `require_once`, jeśli dany plik istnieje. Brak pliku jest zapisywany do logu WordPressa.

## Logowanie i rejestracja

Shortcode’y:

- `[event_login]`
- `[event_registration]`

Logowanie jest obsługiwane na stronie formularza przez `template_redirect` i `wp_signon()`. Formularz używa nonce `event_login_action`. Błąd pozostaje na tej samej stronie; sukces przekierowuje do `home_url('/panel/')`.

`custom-functions.php` blokuje niezalogowanym dostęp do strony `panel` i przekierowuje ich do `wp_login_url()`. Ten mechanizm jest osobnym zabezpieczeniem dostępu do panelu.

## Zasoby i build

Konfiguracja znajduje się w [`package.json`](../themes/hello-elementor-child/package.json) i [`gulpfile.js`](../themes/hello-elementor-child/gulpfile.js).

Źródła:

- `source/scss/` - SCSS frontendowy i administracyjny;
- `source/js/` - źródła JavaScript.

Artefakty:

- `dist/front.min.css`;
- `dist/admin.min.css`;
- `dist/para.min.js`;
- `dist/paraadmin.min.js`.

Dostępna komenda:

```powershell
npm --prefix themes/hello-elementor-child start
```

Uruchamia ona zadanie Gulp `default`, które buduje zasoby, uruchamia BrowserSync i obserwuje zmiany.

## Konfiguracja i ograniczenia

- Wartość `MARATON_VERSION` jest definiowana w `functions.php`.
- Część funkcji używa opcji WordPressa, meta pól wpisów i endpointów AJAX.
- Motyw nie ma własnego test suite ani typechecka.
- Podczas zmian w CSS trzeba rozróżnić ręczny [`style.css`](../themes/hello-elementor-child/style.css) od SCSS i plików `dist`.
- Uprawnienia, nonce’y i przekierowania są częścią kontraktu istniejących formularzy i nie powinny być usuwane przy zmianach wyglądu.

## Raport wydarzenia i PDF

Po upływie siedmiu dni od daty wydarzenia właściciel może złożyć raport z poziomu shortcode’u `[my_events]`. Formularz zapisuje:

- opis przebiegu wydarzenia;
- liczbę podpisanych listów;
- liczbę uczestników;
- informację, czy pobrano materiały;
- dodatkowe uwagi o materiałach;
- link do zdjęć i opcjonalny plik ZIP.

Po zapisaniu raportu panel pokazuje przycisk `Pobierz raport PDF`. Endpoint sprawdza zalogowanie, typ wpisu, właściciela wydarzenia i fakt złożenia raportu. PDF zawiera dane wydarzenia, lokalizację, statystyki, materiały, podsumowanie i link do zdjęć.

Generator jest wersją wstępną bez zewnętrznej biblioteki PDF: tworzy prosty jednostronicowy dokument tekstowy i usuwa znaki diakrytyczne. Dłuższe raporty są ograniczone do pierwszych 43 linii. Docelowo warto zastąpić go biblioteką PDF z obsługą fontów i wielostronicowego składu.
