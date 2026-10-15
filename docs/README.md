# Dokumentacja projektu

Stan dokumentacji: 2026-10-14  
Gałąź: `develop`  
Opisywany revision: `02ddaac` (HEAD)  

Dokumentacja opisuje kod z commita HEAD. Niezatwierdzone zmiany robocze nie są opisane w dokumentach modułów — ich wykaz i ryzyka znajdują się wyłącznie w [status.md](status.md#zmiany-robocze-niezatwierdzone).

Projekt to strona WordPress „Maraton Pisania Listów” (Amnesty International). Dokumentacja obejmuje wyłącznie dwa obszary utrzymywane w tym projekcie:

- motyw potomny [`hello-elementor-child`](../themes/hello-elementor-child/) — wydarzenia, konta organizatorów, panel, materiały, raporty;
- własny plugin [`amnesty-petitions-mk`](../plugins/amnesty-petitions-mk/) — petycje, podpisy, integracja Salesforce.

Elementor, Elementor Pro, ACF, motyw Hello Elementor i pozostałe pluginy są zależnościami środowiska. Są wersjonowane w tym repozytorium, ale ich implementacja nie jest częścią dokumentacji.

## Oznaczenia

W dokumentach stosowane są oznaczenia:

- **Fakt** — wynika bezpośrednio z kodu (podana ścieżka) lub z wykonanej kontroli;
- **Decyzja** — ustalenie potwierdzone przez właściciela projektu;
- **Propozycja** — sugestia, nieuzgodniona;
- **Otwarta kwestia** — brak decyzji lub niezweryfikowane zachowanie.

Treść bez oznaczenia w dokumentach modułów to fakty z kodu.

## Spis treści

- [Architektura i kontrakty](architecture.md)
- [Motyw hello-elementor-child](hello-elementor-child.md)
- [Plugin amnesty-petitions-mk](amnesty-petitions-mk.md)
- [Wdrożenie (WP Engine)](deployment.md)
- [Wymagania i stan realizacji](requirements.md)
- [Stan projektu, decyzje i otwarte kwestie](status.md)

## Repozytorium

Repozytorium git obejmuje tylko katalog `wp-content`. [`.gitignore`](../.gitignore) domyślnie ignoruje wszystko i przepuszcza:

- `docs/`;
- `plugins/` (wszystkie pluginy, także zewnętrzne);
- `themes/hello-elementor/` i `themes/hello-elementor-child/`.

Rdzeń WordPressa, `uploads/`, `mu-plugins/` (obecnie pusty), konfiguracja `wp-config.php` i baza danych nie są w repozytorium.

Instrukcje dla agenta AI pracującego w tym repozytorium: [.github/agents/wordpress-site-maintainer.agent.md](../.github/agents/wordpress-site-maintainer.agent.md) (plik ignorowany przez `.gitignore`, istnieje lokalnie).

## Środowiska

| Środowisko | Stan | Źródło informacji |
|---|---|---|
| Lokalne | Local (Flywheel), adres `http://maraton-pisania-listow.local` | konfiguracja BrowserSync w [`gulpfile.js`](../themes/hello-elementor-child/gulpfile.js) |
| Staging | WP Engine | **Decyzja** (właściciel projektu, 2026-10-14) |
| Produkcja | jeszcze nie istnieje; planowana na WP Engine w tygodniu od 2026-10-19, utworzona ze stagingu | **Decyzja** (właściciel projektu, 2026-10-14) |

Co trzeba przenieść na środowisko, jak zapewnić tabelę podpisów i proponowana lista kontrolna: [deployment.md](deployment.md). **Otwarta kwestia:** sama metoda przenoszenia kodu z repozytorium na staging nie jest opisana.

## Szybki start

Wymagania lokalne: działająca strona w Local (baza danych musi być uruchomiona), Node.js i npm dla buildu motywu.

Build zasobów motywu (Gulp + BrowserSync + watch):

```powershell
npm --prefix themes/hello-elementor-child install
npm --prefix themes/hello-elementor-child start
```

Szczegóły i ograniczenia buildu: [hello-elementor-child.md](hello-elementor-child.md#zasoby-i-build). Plugin petycji nie ma buildu.

## Dostępne kontrole

Testy integracyjne, UI i obciążeniowe są w [`tests/`](../tests/README.md) (od 2026-10-14; bez CI, uruchamiane ręcznie). Projekt nie ma lintera. Tanie kontrole stosowane w projekcie (uruchamiane z katalogu `wp-content`):

```bash
# składnia PHP własnego kodu
find plugins/amnesty-petitions-mk themes/hello-elementor-child -name '*.php' -not -path '*/node_modules/*' -exec php -l {} \;

# składnia JS (bez wykonania)
node --check plugins/amnesty-petitions-mk/assets/js/amnesty-frontend.js
node --check themes/hello-elementor-child/source/js/para.js

# białe znaki w zmianach
git diff --check
```

Zachowanie zależne od WordPressa sprawdza się na lokalnej stronie lub przez WP-CLI.
