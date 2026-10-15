# Testy

Stan: 2026-10-14. Trzy niezależne zestawy:

| Zestaw | Co sprawdza | Gdzie można uruchamiać |
|---|---|---|
| [`integration/`](integration/run.php) | logika motywu i pluginu na prawdziwym WordPressie (106 testów) | lokalnie, staging (bezpieczne) |
| [`ui/`](ui/ui-check.js) | Chrome: czy formularze petycji i rejestracji przechodzą ochronę przed botami (żądania przerywane), logowanie, zakładki panelu, odstępy formularzy | lokalnie, staging (konto testowe) |
| [`load/`](load/loadtest.js) | ruch równoległy: strony, mapa, podpisy | strony: lokalnie; podpisy: **tylko lokalnie** |

## Testy integracyjne (WP-CLI)

```bash
# z katalogu WordPressa (Local: prawy klik na stronie → Open site shell)
wp eval-file wp-content/tests/integration/run.php
```

Kod wyjścia 1, gdy którykolwiek test się nie powiedzie. Zakres: ochrona formularzy przed botami i blokada logowania, uprawnienia eksportu/usuwania podpisów, narzędzia RODO, zgoda marketingowa w Salesforce, ustawienia i zakres edycji, liczniki, „Podpisz wszystkie”, rejestracja → aktywacja → logowanie, zamówienia materiałów, widgety Elementora, log pluginu, daty wydarzeń i migracja flagi mapy, edycja w panelu, shortcode'y, wyszukiwarka Salesforce Topic, synchronizacja Salesforce w tle.

Bezpieczeństwo:

- każdy zapis do bazy jest w transakcji wycofywanej po teście — dane strony się nie zmieniają;
- połączenia zewnętrzne (Salesforce, Nominatim) i e-maile są blokowane albo symulowane;
- log pluginu trafia do katalogu tymczasowego systemu (`AMNESTY_PETITIONS_LOG_DIR`);
- pliki działają tylko przez WP-CLI (wywołanie z przeglądarki kończy się od razu).

Wymaga min. 2 opublikowanych petycji (inaczej część testów jest pomijana — `SKIP`) i konta administratora.

## Test UI (Chrome)

```bash
cd wp-content/tests
npm install
TEST_EMAIL=... TEST_PASSWORD=... PETITION_URL=<adres petycji> npm run ui
# opcjonalnie: BASE_URL=https://maratonpisadev.wpenginepowered.com CHROME_PATH=... SHOTS_DIR=...
```

Konto: osobny subskrybent (nie administrator), np. `wp user create ui-test ui-test@example.invalid --role=subscriber --user_pass=...`, usunięty po teście. Zrzuty ekranu w `ui/shots/` (ignorowane przez git).

## Test obciążenia

```bash
cd wp-content/tests
npm install
PETITION_URL=<adres petycji> npm run load:pages -- 20 15     # 20 równoczesnych połączeń, 15 s na adres
```

Tryb `load:sign` (podpisy z „Podpisz wszystkie”) zapisuje podpisy i uruchamia synchronizację z Salesforce, dlatego:

1. działa tylko na adresie lokalnym (skrypt odmawia innych);
2. wymaga wtyczki symulującej Salesforce: skopiuj [`load/loadtest-helper.php`](load/loadtest-helper.php) do `wp-content/plugins/maraton-loadtest-helper/`, włącz, po teście wyłącz i usuń;
3. po teście usuń podpisy: `wp db query "DELETE FROM $(wp db prefix)amnesty_signatures WHERE email LIKE 'loadtest-%@example.invalid'"`.

Nie uruchamiaj testu obciążenia na WP Engine bez uzgodnienia z ich supportem.

## Wyniki z 2026-10-14 (lokalnie, Local na Windows, bez cache stron)

| Scenariusz | Wynik |
|---|---|
| strony, 20 równocześnie | ~5 stron/s, 0 błędów; strona główna 188 zapytań SQL, inne 116–139 |
| podpis + „Podpisz wszystkie” (3 petycje, Salesforce symulowany 300 ms/wywołanie), synchronicznie — przed zmianą | 1 osoba: 3,9 s; 10 równocześnie: mediana 11,8 s, p90 19,6 s |
| to samo z synchronizacją w tle — po zmianie | 1 osoba: 0,17 s; 10 równocześnie: mediana 1,0 s; 50 równocześnie: mediana 2,1 s, p99 3,8 s, 0 błędów |
| kolejka w tle | 435 podpisów zsynchronizowanych, 0 błędów, 0 duplikatów; ~2,3 podpisu/s przy 300 ms/wywołanie |

Szacunek dla 1000 odwiedzających dziennie: [docs/status.md](../docs/status.md#obciążenie).
