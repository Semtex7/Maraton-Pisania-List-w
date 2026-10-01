# Stan projektu i otwarte kwestie

Stan na: 2026-10-01  
Gałąź: `develop`  
Revision bazowy: `f7c0a06`  

## Potwierdzone kontrole

- `php -l` przechodzi dla własnych plików PHP w pluginie i motywie.
- Lokalna strona `maraton-pisania-listow.local` odpowiada.
- Formularz logowania pozostaje na stronie po błędnych danych i pokazuje komunikat.
- Widok pojedynczej petycji został sprawdzony w przeglądarce desktopowej i mobilnej.
- Formularz petycji ma osobny wariant mobilny.
- Ekran podziękowania używa dynamicznego licznika podpisów i konfigurowalnych przycisków.
- `git diff --check` nie wykazuje błędów treści; Git zgłasza jedynie ostrzeżenie o konwersji LF/CRLF w pliku CSS motywu.

## Aktualne zmiany robocze

Sześć plików jest zmodyfikowanych lokalnie i nie należy ich traktować jako części ostatniego commita:

- `plugins/amnesty-petitions-mk/assets/css/petitions-frontend.css`;
- `plugins/amnesty-petitions-mk/includes/settings.php`;
- `plugins/amnesty-petitions-mk/includes/shortcodes.php`;
- `themes/hello-elementor-child/functions/enqueue-scripts.php`;
- `themes/hello-elementor-child/functions/events-auth.php`;
- `themes/hello-elementor-child/style.css`.

Dokumentacja opisuje te zmiany jako aktualny stan roboczy, ale nie potwierdza ich jeszcze historią Git.

## Brakujące kontrole

- Brak testów automatycznych i CI.
- Nie wykonano pełnego buildu Gulp jako części dokumentowania.
- Wykonano testowy podpis na lokalnej petycji z połączeniem do Salesforce Sandbox; panel podpisów pokazał status synchronizacji.
- Zweryfikowano poprawne logowanie kontem testowym i przekierowanie do `/panel/`.
- Zweryfikowano rozpoczęcie pobierania raportu PDF z panelu właściciela wydarzenia.
- Lokalne narzędzie przeglądarkowe zgłaszało błąd certyfikatu dla części zasobów HTTPS z `uploads`; nie zmieniano tego w ramach dokumentacji.

## Otwarte kwestie

- Czy wygenerowane pliki `dist/` mają być utrzymywane ręcznie w commitach, czy zawsze odtwarzane z `source/` przez Gulp?
- Czy konfiguracja Salesforce powinna mieć osobny opis procedury wdrożenia poza tym repozytorium?
- Czy istniejące README w kopiach `wp-content/` i `wp-content-1/` powinny zostać usunięte albo zastąpione jednym źródłem prawdy? Nie zmieniono ich, ponieważ nie należą do zakresu dokumentowanego motywu i pluginu.
- Brak potwierdzonego procesu produkcyjnego deployu, migracji tabeli podpisów i rotacji konfiguracji zewnętrznych.
- Generator PDF raportu jest wersją wstępną: tekstową, jednostronicową i bez pełnej obsługi polskich znaków/fontów.
