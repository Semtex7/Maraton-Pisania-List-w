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

## Wysyłka e-maili

Stan na 2026-10-14 (staging `maratonpisadev.wpenginepowered.com`): maile (aktywacja konta, reset hasła) wychodzą z domyślnego adresu WordPressa `wordpress@<domena strony>`, czyli `wordpress@maratonpisadev.wpenginepowered.com`, a Gmail pokazuje „przez wpenginepowered.com”.

**Fakty (DNS amnesty.org.pl, sprawdzone 2026-10-14):**

- SPF: `v=spf1 mx include:spf.protection.outlook.com include:_spf.jupiter.salesmanago.pl include:mailing.bitcarvers.com -all` — serwery WP Engine nie są autoryzowane;
- DMARC: `p=none` (raporty na `emailbouncesnoreply@amnesty.org.pl`);
- poczta domeny: Microsoft 365 (`amnesty-org-pl.mail.protection.outlook.com`).

**Co się stanie na produkcji bez zmian:** WordPress użyje `wordpress@<domena produkcji>` (np. `wordpress@maraton.amnesty.org.pl`), wysyłka nadal pójdzie przez WP Engine — adres nie będzie autoryzowany (SPF/DKIM), więc maile mogą trafiać do spamu i nadal mieć dopisek „przez …”. Ustawienie samego nagłówka `From: …@amnesty.org.pl` w kodzie pogorszyłoby sytuację (SPF `-all`, brak DKIM).

**Kod (zmiany robocze 2026-10-14):** nazwa nadawcy „Maraton Pisania Listów – Amnesty International” zamiast „WordPress” (`maraton_mail_from_name()` w [`custom-functions.php`](../themes/hello-elementor-child/functions/custom-functions.php)); podpis maila aktywacyjnego „Zespół Amnesty International”. Adresu nadawcy kod nie zmienia.

**Propozycja (do decyzji z IT Amnesty):** wysyłka przez Microsoft 365 z dedykowanej skrzynki (np. `maraton@amnesty.org.pl`, adres już używany na stronie) za pomocą wtyczki SMTP (np. WP Mail SMTP, mailer Microsoft 365 / Outlook) — SPF już obejmuje Microsoft 365, nadawca będzie zgodny z domeną. Alternatywa: usługa transakcyjna dopisana do SPF i z kluczem DKIM dla domeny. Po konfiguracji: test aktywacji konta i resetu hasła, sprawdzenie nagłówków (SPF/DKIM/DMARC = pass).

## Synchronizacja Salesforce w tle

Od zmian roboczych z 2026-10-14 podpisy są wysyłane do Salesforce przez WP-Cron ([`salesforce-sync.php`](../plugins/amnesty-petitions-mk/includes/salesforce-sync.php)): zaraz po podpisie i co 5 minut. Status każdego podpisu widać w Petitions → Podpisy (✅ zsynchronizowany, ⏳ w kolejce, ❌ nieudany po 5 próbach, — petycja bez Topic).

Przy wdrożeniu:

1. **WP-Cron musi działać.** Domyślnie WordPress uruchamia go przy odwiedzinach strony. Na WP Engine zalecane jest włączenie crona po stronie serwera (User Portal → środowisko → Utilities / „WP Engine Alternate Cron” lub zgłoszenie do supportu) — **do sprawdzenia** na stagingu: po podpisie testowym status powinien zmienić się z ⏳ na ✅ w ciągu kilku minut.
2. **Schemat tabeli aktualizuje się sam** przy pierwszym żądaniu po wdrożeniu (`amnesty_petitions_db_version` 1 → 2: kolumna `salesforce_attempts`). Podpisy, które były niezsynchronizowane przed aktualizacją, nie są dodawane do kolejki.
3. **Przed utworzeniem produkcji ze stagingu** usuń testowe podpisy ze stagingu (lub tabelę z kopii produkcyjnej) — inaczej podpisy testowe dodane po wdrożeniu kolejki zostaną wysłane do Salesforce produkcyjnego po zmianie danych API.
4. Ręczne uruchomienie kolejki: `wp eval 'print_r( amnesty_sf_process_queue() );'`.

## Testy przed wdrożeniem

[tests/README.md](../tests/README.md): `wp eval-file wp-content/tests/integration/run.php` (bezpieczne także na stagingu — zmiany są wycofywane), test UI z kontem testowym. Katalog `tests/` jest w repozytorium; pliki PHP działają tylko przez WP-CLI. Jeśli nie ma trafiać na serwer, dopisz `wp-content/tests/` do `.wpe-push-ignore`.

## Ochrona formularzy i zgoda w Salesforce — do sprawdzenia na stagingu

- **Limity na adres IP** (petycja 60 / 10 min, rejestracja 5 / h, logowanie 10 błędów / 15 min) zakładają, że WordPress widzi prawdziwy adres odwiedzającego w `REMOTE_ADDR`. Na WP Engine tak powinno być (proxy przekazuje adres klienta) — **do sprawdzenia**: jeśli wszyscy dostaliby ten sam adres, limity zadziałałyby globalnie. Limity zmienia się filtrami `amnesty_petition_rate_limit` i `maraton_form_limits`.
- **Pole zgody marketingowej:** Petitions → Ustawienia → Salesforce API → „Pole zgody marketingowej”, domyślnie `Newsletter_Subscribe__c` (pole istnieje w sandboxie, sprawdzone 2026-10-14). **Do potwierdzenia z administratorem Salesforce**, czy to właściwe pole (alternatywy w sandboxie: `Use_Personal_Data__c`, `Interest_Involvement__c`) i czy istnieje na produkcji. Po podpisie testowym ze zgodą sprawdź pole w Contact.
- **Do rozważenia z administratorem Salesforce:** pola `Last_Petition__c`, `Last_Petition_Date__c`, `First_Petition__c`, `petition_source__c` w Contact wyglądają na używane przez inne formularze petycji Amnesty — Maraton może je uzupełniać tak samo (**propozycja**, niezaimplementowane).
