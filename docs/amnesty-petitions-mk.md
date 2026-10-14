# Plugin amnesty-petitions-mk

Stan: 2026-10-14, `develop` @ `02ddaac`. Zmiany robocze: [status.md](status.md#zmiany-robocze-niezatwierdzone).

## Rola

Plugin „Amnesty Petitions” obsługuje petycje: wpisy, formularz podpisu, zapis podpisów, ekran podziękowania, ustawienia, listę i eksport podpisów, widgety Elementora i synchronizację z Salesforce.

Punkt wejścia: [`amnesty-petitions.php`](../plugins/amnesty-petitions-mk/amnesty-petitions.php). Stała `AMNESTY_PETITIONS_VERSION` = `2.0.8` (nagłówek pluginu podaje `2.0.0`).

## Moduły

| Plik | Odpowiedzialność |
|---|---|
| `includes/database.php` | tabela podpisów (`dbDelta` przy aktywacji), `flush_rewrite_rules` |
| `includes/cpt-meta.php` | CPT `amnesty_petition`, metabox i zapis meta, kolumny listy |
| `includes/settings.php` | strona „Ustawienia” |
| `includes/shortcodes.php` | `[amnesty_petition]`, `[amnesty_petition_map]` |
| `includes/ajax-salesforce.php` | zapis podpisu, Salesforce, log `amnesty-debug.log` |
| `includes/ajax-map.php` | AJAX danych mapy — **nie jest ładowany** (`require_once` zakomentowany) |
| `includes/admin-signatures.php` | strona „Podpisy” i eksport CSV |
| `includes/elementor-widgets.php` | kategoria „Amnesty Petycje” i widgety |
| `templates/template-tags.php` | `amnesty_get_petition_card_html()` — HTML karty petycji |
| `templates/single-amnesty_petition.php` | szablon pojedynczej petycji |
| `assets/css/petitions-frontend.css`, `assets/js/amnesty-frontend.js` | front formularza (ręczny CSS/JS, bez buildu) |

## CPT i meta petycji

`amnesty_petition`: publiczny, archiwum, slug `petycja`, wsparcie `title`, `editor`, `thumbnail`, `excerpt`. Menu w adminie: „Petitions”.

Meta (zapisywane przez metabox w [`cpt-meta.php`](../plugins/amnesty-petitions-mk/includes/cpt-meta.php)):

| Klucz | Znaczenie |
|---|---|
| `_amnesty_petition_topic_name` | nazwa Topic w Salesforce; pusta = brak synchronizacji |
| `_amnesty_petition_country`, `_amnesty_petition_addressee` | kraj, adresat |
| `_amnesty_petition_target` | cel liczby podpisów (karta) |
| `_amnesty_petition_extra_letters` | liczba listów doliczana do licznika (np. papierowych) |
| `_amnesty_petition_appeal`, `_amnesty_petition_solidarity_letter` | treści dwóch typów listu |
| `_amnesty_petition_short_description`, `_amnesty_petition_top_content` | treści szablonu |
| `_amnesty_petition_video_link`, `_amnesty_petition_materials` | wideo, materiały |
| `_amnesty_petition_show_map` | pokazanie mapy sygnatariuszy |

Licznik = `COUNT` rekordów petycji w tabeli + `_amnesty_petition_extra_letters`.

## Tabela podpisów

`{$wpdb->prefix}amnesty_signatures` ([`database.php`](../plugins/amnesty-petitions-mk/includes/database.php)):

`id`, `petition_id`, `first_name`, `last_name`, `email`, `phone`, `city`, `letter_type` (domyślnie `appeal`), `custom_message`, `campaign_consent`, `newsletter_consent`, `signed_at`, `salesforce_synced`. Indeksy: `petition_id`, `email`.

Tabela powstaje/aktualizuje się **tylko przy aktywacji pluginu**. Zmiana schematu wymaga ponownej aktywacji (lub innej migracji) na każdym środowisku.

## Renderowanie pojedynczej petycji

Filtr `single_template` używa `single-amnesty_petition.php` z motywu, jeśli istnieje, w przeciwnym razie szablonu pluginu. Szablon renderuje hero (obraz, tytuł, kraj, opis, wideo), formularz `[amnesty_petition id]` i — gdy `_amnesty_petition_show_map` — `[amnesty_petition_map id]`.

`[amnesty_petition]` pozwala wybrać typ listu (apel / list solidarnościowy), edytować treść, podać dane i zgody. Opcjonalna zgoda „podpisz wszystkie” (`sign_all`).

## Zapis podpisu

[`ajax-salesforce.php`](../plugins/amnesty-petitions-mk/includes/ajax-salesforce.php), akcja `submit_amnesty_petition` (także dla niezalogowanych):

1. weryfikacja nonce `amnesty_sign_petition` (pole `amnesty_sign_nonce`), wymaganych pól i `is_email`;
2. `sign_all` = podpis pod wszystkimi opublikowanymi petycjami, w przeciwnym razie tylko pod bieżącą;
3. dla każdej petycji: pominięcie, jeśli ten e-mail już ją podpisał; zapis rekordu;
4. jeśli petycja ma `_amnesty_petition_topic_name`: token OAuth (`client_credentials`) → wyszukanie lub utworzenie `Topic` → wyszukanie po e-mailu lub utworzenie `Contact` → `TopicAssignment`; sukces ustawia `salesforce_synced = 1`;
5. odpowiedź JSON; błąd, gdy e-mail już podpisał petycję główną (lub wszystkie wybrane).

Do Salesforce trafiają imię, nazwisko, e-mail, telefon, miasto. Zgody i typ listu zostają tylko w tabeli. Token jest pobierany przy każdej synchronizacji. Nieudana synchronizacja nie jest ponawiana — rekord zostaje z `salesforce_synced = 0`.

W HEAD błędy są dopisywane do `amnesty-debug.log` w katalogu pluginu (zawiera m.in. adresy e-mail). Zmiana robocza przenosi log do `_wpeprivate/` — [deployment.md](deployment.md#log-pluginu).

## Mapa sygnatariuszy

`[amnesty_petition_map]` ładuje Leaflet z `unpkg.com` i pobiera dane z `admin-ajax.php?action=get_amnesty_map_data`. Handler tej akcji jest w `ajax-map.php`, który w HEAD nie jest ładowany, a widget mapy jest wyrejestrowany. **Otwarta kwestia:** włączenie `_amnesty_petition_show_map` prawdopodobnie da pustą mapę (nie zweryfikowano w przeglądarce).

## Administracja

Pod menu „Petitions”:

- **Ustawienia** ([`settings.php`](../plugins/amnesty-petitions-mk/includes/settings.php), `manage_options`): grupa `amnesty_global_settings_group` — kolory przycisków (kolory markera mapy są zarejestrowane, ale ich pola są zakomentowane), teksty RODO i zgód, popup, teksty i linki ekranu podziękowania, link prywatności, `amnesty_sf_instance_url`, `amnesty_sf_client_id`, `amnesty_sf_client_secret`. Opcja `amnesty_text_consent_sign_all` jest odczytywana, ale nie ma pola w ustawieniach — zawsze działa tekst domyślny z kodu.
- **Podpisy** ([`admin-signatures.php`](../plugins/amnesty-petitions-mk/includes/admin-signatures.php), `manage_options`): lista podpisów i akcja masowa „Eksportuj do CSV” (separator `;`, UTF-8 z BOM).

## Widgety Elementora

Kategoria „Amnesty Petycje”, rejestrowana tylko gdy Elementor jest załadowany:

- `Amnesty_Petition_Form_Widget` — formularz;
- `Amnesty_Petition_Card_Widget` — karta petycji;
- `Amnesty_Petition_Map_Widget` — klasa istnieje, rejestracja zakomentowana.

## Ekran podziękowania

Stan `#thankyou` ukrywa formularz i pokazuje tytuł, podtytuł, licznik listów, nagłówek akcji i trzy przyciski (wydarzenia, znaczek, dalsze działania) — teksty i linki z opcji `amnesty_thankyou_*`.

## Zasoby

CSS i JS pluginu są ładowane tylko na `is_singular('amnesty_petition')`, z wersją z `filemtime`. JS ładowany w stopce, dostaje `amnestyAjax` (`ajaxurl`, `nonce`, komunikaty błędów).

Formularz zawiera inline style, dlatego zmiany CSS muszą uwzględniać kaskadę i reguły `!important`.
