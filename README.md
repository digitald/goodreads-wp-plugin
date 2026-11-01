# Goodreads RSS Widget

Plugin WordPress leggero che mostra i libri di uno scaffale pubblico [Goodreads](https://www.goodreads.com/).

Per ogni libro visualizza:

- titolo
- autore
- copertina
- valutazione personale con stelline (solo se presente)
- link alla scheda del libro su Goodreads

Da ottobre 2026 Goodreads ha rimosso i feed RSS. Il plugin legge il widget ufficiale dello scaffale (`/review/custom_widget/{id}`), lo stesso che Goodreads usa per l'incorporamento su siti esterni. Il profilo deve essere pubblico. L'anno di pubblicazione non è più disponibile.

## Requisiti e compatibilità

| Requisito | Versione minima |
|-----------|-----------------|
| WordPress | 5.8 |
| PHP | 7.4 |
| Testato fino a | WordPress 6.8 |

Servono l'**ID del profilo** (il numero in `goodreads.com/user/show/12345-nome`) e lo **slug dello scaffale** (`read`, `currently-reading`, `to-read`, o uno scaffale personalizzato).

## Limiti

| Limite | Valore |
|--------|--------|
| Libri per shortcode | 1–50 |
| Cache scaffale | 24 ore (1 ora se lo scaffale è vuoto) |
| Fetch esterni | solo `www.goodreads.com` |
| Profilo | deve essere pubblico |
| Stelline | solo la valutazione personale; se assente, nessuna stella |
| Anno di pubblicazione | non disponibile |

## Installazione

1. Copia la cartella in `wp-content/plugins/goodreads-plugin/`.
2. Attiva **Goodreads RSS Widget** in WordPress.
3. Copia l'ID del profilo Goodreads e lo slug dello scaffale.
4. Incollali in **Impostazioni → Goodreads**.
5. Inserisci lo shortcode in pagina o widget.

## Shortcode

```
[goodreads]
[goodreads count="6"]
[goodreads shelf="currently-reading" count="6"]
```

- `count` (opzionale): numero libri da mostrare (1–50). Default dalle impostazioni admin.
- `shelf` (opzionale): slug dello scaffale. Default dalle impostazioni admin.

L'ID del profilo si imposta solo in admin.

## Widget

1. **Aspetto → Widget** (o Personalizza → Widget)
2. Aggiungi blocco **Shortcode**
3. Incolla `[goodreads count="6"]`
4. Salva

## Sicurezza

- Il profilo deve essere pubblico. La vecchia chiave RSS non serve più e, se era salvata, viene eliminata al primo salvataggio delle nuove impostazioni.
- Validazione host: solo `goodreads.com` / `www.goodreads.com`.
- Output escapato; nessun HTML raw dal widget.
- `uninstall.php` rimuove opzioni e cache alla disinstallazione.

## Struttura del progetto

```
goodreads-plugin/
├── goodreads-feed.php
├── includes/
│   ├── class-feed.php
│   └── class-shortcode.php
├── assets/style.css
├── uninstall.php
├── readme.txt
└── README.md
```

## Changelog

### 1.1.0

- Goodreads ha rimosso i feed RSS (ottobre 2026): lo scaffale si legge dal widget pubblico
- Impostazioni: ID profilo e slug dello scaffale, al posto dell'URL RSS
- Lo shortcode accetta `shelf`
- Cache di 24 ore; un errore di rete non viene messo in cache
- L'anno di pubblicazione non viene più mostrato

### 1.0.0

- Prima release stabile
- Shortcode `[goodreads]` per pagine e widget
- Impostazioni admin con URL RSS e help inline
- Cache transient (1 ora)
- Validazione URL anti-SSRF
- Documentazione stile WordPress.org

## Licenza

GPLv2 or later — [GNU GPL v2.0](https://www.gnu.org/licenses/gpl-2.0.html)
