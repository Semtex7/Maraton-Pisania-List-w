# Plugin amnesty-petitions-mk

## Rola

`amnesty-petitions-mk` obsługuje petycje Amnesty: wpisy petycji, formularz podpisu, zapis podpisów, ekran podziękowania, ustawienia administracyjne, mapę, widget Elementora i integrację Salesforce.

Punkt wejścia: [`amnesty-petitions.php`](../plugins/amnesty-petitions-mk/amnesty-petitions.php).

## Moduły

- `database.php` - tabela podpisów i instalacja struktury danych;
- `cpt-meta.php` - meta pola petycji i zapis danych wpisu;
- `settings.php` - strona `Ustawienia` pod menu CPT petycji;
- `shortcodes.php` - shortcode `[amnesty_petition]` oraz render formularza i podziękowania;
- `ajax-salesforce.php` - obsługa zapisu podpisu i wysyłka do Salesforce;
- `admin-signatures.php` - lista podpisów i eksport administracyjny;
- `elementor-widgets.php` - rejestracja widgetów Elementora;
- `templates/template-tags.php` - HTML kart petycji;
- `templates/single-amnesty_petition.php` - szablon pojedynczej petycji;
- `assets/css/petitions-frontend.css` - style frontendowe;
- `assets/js/amnesty-frontend.js` - frontendowa obsługa formularza i modali.

## CPT i dane petycji

Plugin używa CPT `amnesty_petition`. Pojedyncza petycja może mieć m.in. następujące meta pola:

- `_amnesty_petition_country`;
- `_amnesty_petition_target`;
- `_amnesty_petition_extra_letters`;
- `_amnesty_petition_appeal`;
- `_amnesty_petition_solidarity_letter`;
- `_amnesty_petition_short_description`;
- `_amnesty_petition_video_link`;
- `_amnesty_petition_materials`.

Licznik podpisów jest liczony jako liczba rekordów petycji w tabeli `{$wpdb->prefix}amnesty_signatures` plus `_amnesty_petition_extra_letters`.

## Renderowanie pojedynczej petycji

Plugin wybiera szablon `single-amnesty_petition.php` przez filtr `single_template`. Szablon renderuje:

1. sekcję hero z obrazem, tytułem, krajem, opisem i opcjonalnym wideo;
2. formularz `[amnesty_petition id="..."]`;
3. opcjonalną mapę sygnatariuszy;
4. pozostałe elementy zależne od meta pól.

Formularz pozwala wybrać typ listu, edytować treść, podać dane sygnatariusza i udzielić zgód.

## Zapis podpisu

Frontend przechwytuje submit formularza i wysyła `FormData` do `admin-ajax.php`. Żądanie zawiera nonce `amnesty_sign_petition`. Po sukcesie interfejs przechodzi do `#thankyou`; po błędzie pokazuje komunikat w formularzu.

Dane są zapisywane w tabeli podpisów, a dla skonfigurowanych przypadków przekazywane dalej do Salesforce. Szczegóły połączenia są zależne od opcji środowiska; wartości sekretów nie są częścią dokumentacji.

## Ustawienia administracyjne

Plugin rejestruje stronę ustawień pod menu CPT petycji:

`Wydarzenia/petycje -> Ustawienia`

Ustawienia obejmują:

- kolory elementów;
- teksty RODO i zgód;
- tytuł i opis starszego popupu;
- teksty ekranu podziękowania;
- etykiety trzech przycisków podziękowania;
- adresy przycisków wydarzeń, znaczka i dalszych działań;
- link prywatności;
- konfigurację Salesforce.

Wartości są rejestrowane przez WordPress Settings API i sanitizowane zależnie od typu pola.

## Ekran podziękowania

Stan `#thankyou` ukrywa formularz i pokazuje:

- tytuł;
- podtytuł;
- aktualny licznik listów;
- tekst zachęty;
- trzy konfigurowalne przyciski.

Style są responsywne. Na mobile formularz petycji przechodzi do jednej kolumny, zakładki typów listu są ułożone pionowo, a zgody mają wspólny układ checkboxa i tekstu.

## Zasoby

Style są ładowane tylko na stronach pojedynczego CPT `amnesty_petition`. Wersja zasobu CSS i JavaScript jest ustalana na podstawie `filemtime`, jeśli plik istnieje. Skrypt frontendowy jest ładowany w stopce i otrzymuje przez `wp_localize_script` adres AJAX oraz nonce.

## Ograniczenia

- Brak automatycznych testów pluginu.
- Integracja Salesforce wymaga zewnętrznego środowiska i konfiguracji.
- Część HTML formularza zawiera istniejące inline style, dlatego zmiany CSS muszą uwzględniać kaskadę i reguły `!important`.
- Tabela podpisów jest zależna od prefixu WordPressa i nie powinna być adresowana stałą nazwą.
