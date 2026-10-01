# Dokumentacja projektu

Stan dokumentacji: 2026-10-01  
Gałąź: `develop`  
Ostatni commit: `f7c0a06`  

Dokumentacja obejmuje wyłącznie dwa obszary utrzymywane w tym projekcie:

- motyw potomny [`hello-elementor-child`](../themes/hello-elementor-child/);
- własny plugin [`amnesty-petitions-mk`](../plugins/amnesty-petitions-mk/).

Elementor, Elementor Pro, ACF, motyw Hello Elementor i pozostałe pluginy są tutaj traktowane wyłącznie jako zależności środowiska. Ich wewnętrzna implementacja nie jest częścią tej dokumentacji.

## Spis treści

- [Architektura](architecture.md)
- [Motyw hello-elementor-child](hello-elementor-child.md)
- [Plugin amnesty-petitions-mk](amnesty-petitions-mk.md)
- [Stan projektu i otwarte kwestie](status.md)

## Szybki start

Projekt działa jako instalacja WordPressa. Lokalny adres używany podczas weryfikacji to:

`http://maraton-pisania-listow.local`

Motyw potomny ma skrypt Gulp uruchamiany przez:

```powershell
npm --prefix themes/hello-elementor-child start
```

Skrypt `start` uruchamia watch/build oraz BrowserSync zgodnie z konfiguracją w [`gulpfile.js`](../themes/hello-elementor-child/gulpfile.js). Plugin petycji nie ma własnego skryptu instalacyjnego ani testowego.
