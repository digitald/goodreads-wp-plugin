=== Goodreads RSS Widget ===
Contributors: digitald
Tags: goodreads, rss, books, widget, shortcode, reading
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mostra i libri da uno scaffale Goodreads tramite feed RSS con titolo, autore, anno, copertina e valutazione.

== Description ==

Goodreads RSS Widget legge il feed RSS di uno scaffale Goodreads (es. currently-reading, read, to-read) e mostra i libri in una griglia responsive.

Per ogni libro vengono mostrati:

* titolo
* autore
* anno di pubblicazione
* copertina
* valutazione personale con stelline (solo se user_rating > 0)
* link alla recensione su Goodreads

Il plugin non usa API ufficiali Goodreads: legge solo il feed RSS privato dello scaffale.

= Limiti =

* Richiede l'URL RSS completo di Goodreads, incluso il parametro `key`.
* Numero massimo di libri per shortcode: 50.
* Cache del feed: 1 ora.
* Compatibile con WordPress 5.8+ e PHP 7.4+.
* Lo shortcode non accetta l'URL RSS come attributo (per non esporre la chiave segreta).

= Shortcode =

`[goodreads]`

Attributi opzionali:

* `count` — numero di libri da mostrare (1-50)

= Widget =

Lo shortcode può essere inserito in sidebar o footer tramite il blocco Shortcode di WordPress.

== Installation ==

1. Carica la cartella del plugin in `/wp-content/plugins/goodreads-plugin/`.
2. Attiva il plugin dalla schermata Plugin di WordPress.
3. Su Goodreads vai in My Books, apri uno scaffale e clicca RSS in basso a sinistra.
4. Copia l'URL completo e incollalo in `Impostazioni -> Goodreads`.
5. Inserisci `[goodreads]` in una pagina, un articolo o un widget.

== Frequently Asked Questions ==

= Dove trovo l'URL RSS? =

Su Goodreads: My Books → scegli uno scaffale → link RSS in basso a sinistra.

= Posso cambiare scaffale? =

Sì. Ogni URL RSS corrisponde a uno scaffale (es. `shelf=currently-reading`). Sostituisci l'URL nelle impostazioni.

= Perché non vedo le stelline? =

Il plugin mostra solo la tua valutazione (`user_rating`). Su scaffali come currently-reading spesso è 0 finché non valuti il libro.

= L'URL RSS è sicuro da condividere? =

No. Contiene una chiave privata. Conservalo solo nelle impostazioni admin del plugin.

== Changelog ==

= 1.0.0 =
* Prima release stabile.
* Shortcode `[goodreads]` per pagine e widget.
* Impostazioni admin con URL RSS completo e help inline.
* Cache transient del feed (1 ora).
* Validazione URL solo verso goodreads.com.
* Requisiti minimi: WordPress 5.8+ e PHP 7.4+.

== Upgrade Notice ==

= 1.0.0 =
Prima release stabile del plugin.
