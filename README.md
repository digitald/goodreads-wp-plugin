# Goodreads RSS Widget

Plugin WordPress leggero che mostra i libri da uno scaffale [Goodreads](https://www.goodreads.com/) tramite feed RSS.

Per ogni libro visualizza:

- titolo
- autore
- anno di pubblicazione
- copertina
- valutazione personale con stelline (solo se `user_rating > 0`)
- link alla recensione su Goodreads

Non richiede API key Goodreads: usa l'URL RSS dello scaffale, che include già scaffale e chiave di accesso.

## Requisiti e compatibilità

| Requisito | Versione minima |
|-----------|-----------------|
| WordPress | 5.8 |
| PHP | 7.4 |
| Testato fino a | WordPress 6.8 |

Serve l'**URL RSS completo** di uno scaffale Goodreads (My Books → scaffale → RSS).

## Limiti

| Limite | Valore |
|--------|--------|
| Libri per shortcode | 1–50 |
| Cache feed RSS | 1 ora |
| Fetch esterni | solo `goodreads.com` |
| URL nello shortcode | non supportato (sicurezza) |
| Stelline | solo `user_rating`; se 0, nessuna stella |

## Installazione

1. Copia la cartella in `wp-content/plugins/goodreads-plugin/`.
2. Attiva **Goodreads RSS Widget** in WordPress.
3. Su Goodreads: **My Books** → apri uno scaffale → clicca **RSS**.
4. Incolla l'URL completo in **Impostazioni → Goodreads**.
5. Inserisci lo shortcode in pagina o widget.

## Shortcode

```
[goodreads]
[goodreads count="6"]
```

- `count` (opzionale): numero libri da mostrare (1–50). Default dalle impostazioni admin.

L'URL RSS va configurato solo in admin, mai nello shortcode.

## Widget

1. **Aspetto → Widget** (o Personalizza → Widget)
2. Aggiungi blocco **Shortcode**
3. Incolla `[goodreads count="6"]`
4. Salva

## Sicurezza

- L'URL RSS contiene una **chiave segreta** (`key=...`): trattalo come una password.
- Salvato solo nelle opzioni WordPress, accessibili agli amministratori.
- Validazione host: solo `goodreads.com` / `www.goodreads.com`.
- Output escapato; nessun HTML raw dal feed.
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

### 1.0.0

- Prima release stabile
- Shortcode `[goodreads]` per pagine e widget
- Impostazioni admin con URL RSS e help inline
- Cache transient (1 ora)
- Validazione URL anti-SSRF
- Documentazione stile WordPress.org

## Licenza

GPLv2 or later — [GNU GPL v2.0](https://www.gnu.org/licenses/gpl-2.0.html)
