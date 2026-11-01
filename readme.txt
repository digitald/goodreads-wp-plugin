=== Goodreads RSS Widget ===
Contributors: digitald
Tags: goodreads, rss, books, widget, shortcode, reading
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mostra i libri di uno scaffale Goodreads pubblico con titolo, autore, copertina e valutazione.

== Description ==

Goodreads RSS Widget mostra i libri di uno scaffale Goodreads pubblico (es. currently-reading, read, to-read) in una griglia responsive.

Per ogni libro vengono mostrati:

* titolo
* autore
* copertina
* valutazione personale con stelline (solo se presente)
* link alla scheda del libro su Goodreads

Da ottobre 2026 Goodreads non pubblica più i feed RSS. Il plugin legge il widget ufficiale dello scaffale. Il profilo deve essere pubblico. L'anno di pubblicazione non è disponibile.

= Limiti =

* Richiede l'ID di un profilo Goodreads pubblico e lo slug dello scaffale.
* Numero massimo di libri per shortcode: 50.
* Cache dello scaffale: 24 ore.
* Compatibile con WordPress 5.8+ e PHP 7.4+.
* L'ID del profilo si imposta solo in admin. Lo shortcode può sovrascrivere scaffale e numero.

= Shortcode =

`[goodreads]`

Attributi opzionali:

* `count` — numero di libri da mostrare (1-50)
* `shelf` — slug dello scaffale, ad esempio `currently-reading`

= Widget =

Lo shortcode può essere inserito in sidebar o footer tramite il blocco Shortcode di WordPress.

== Installation ==

1. Carica la cartella del plugin in `/wp-content/plugins/goodreads-plugin/`.
2. Attiva il plugin dalla schermata Plugin di WordPress.
3. Copia l'ID numerico dal tuo profilo Goodreads (`goodreads.com/user/show/12345678-nome`) e lo slug dello scaffale.
4. Incollali in `Impostazioni -> Goodreads`.
5. Inserisci `[goodreads]` in una pagina, un articolo o un widget.

== Frequently Asked Questions ==

= Dove trovo l'ID del profilo? =

Nell'indirizzo del profilo: `goodreads.com/user/show/12345678-nome`. Il numero è l'ID. Puoi incollare anche l'URL intero.

= Posso cambiare scaffale? =

Sì. In impostazioni, oppure nello shortcode: `[goodreads shelf="currently-reading"]`.

= Perché non vedo le stelline? =

Il plugin mostra solo la tua valutazione. Se non hai dato un voto, le stelline non compaiono.

= Il profilo è privato? =

No, non funziona. Goodreads ha rimosso i feed RSS, quindi un profilo privato non è più leggibile dal plugin.

== Changelog ==

= 1.1.0 =
* Goodreads ha rimosso i feed RSS: lo scaffale si legge dal widget pubblico.
* Impostazioni con ID profilo e slug dello scaffale.
* Attributo shortcode `shelf`.
* Cache di 24 ore. Un errore di rete non viene messo in cache.
* L'anno di pubblicazione non viene più mostrato.

= 1.0.0 =
* Prima release stabile.
* Shortcode `[goodreads]` per pagine e widget.
* Impostazioni admin con URL RSS completo e help inline.
* Cache transient del feed (1 ora).
* Validazione URL solo verso goodreads.com.
* Requisiti minimi: WordPress 5.8+ e PHP 7.4+.

== Upgrade Notice ==

= 1.1.0 =
I feed RSS di Goodreads non esistono più. Dopo l'aggiornamento indica l'ID del profilo e lo scaffale in Impostazioni → Goodreads. Se era salvato il vecchio URL RSS, l'ID e lo scaffale vengono riconosciuti finché non salvi di nuovo.

= 1.0.0 =
Prima release stabile del plugin.
