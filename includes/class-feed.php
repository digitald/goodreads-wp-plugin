<?php
/**
 * Fetches a public Goodreads shelf from the official shelf widget.
 *
 * Goodreads removed RSS in October 2026. The shelf widget at
 * /review/custom_widget/{user_id} is still the embed Goodreads serves
 * for external sites, and it includes title, author, cover and rating.
 *
 * @package Goodreads_RSS_Widget
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles profile validation, widget retrieval, caching, and parsing.
 *
 * @since 1.0.0
 */
class Goodreads_Feed {

	/** @var string Option name for the numeric Goodreads user id. */
	const OPT_USER_ID = 'goodreads_user_id';

	/** @var string Option name for the shelf slug. */
	const OPT_SHELF = 'goodreads_shelf';

	/** @var string Option name for the default book count. */
	const OPT_COUNT = 'goodreads_default_count';

	/** @var string Legacy option that stored the RSS URL, including its secret key. */
	const OPT_LEGACY_FEED_URL = 'goodreads_feed_url';

	/** @var string Prefix for WordPress transient cache keys. */
	private const TRANSIENT_PREFIX = 'goodreads_feed_';

	/** @var string Allowed Goodreads hostnames. */
	private const ALLOWED_HOSTS = array( 'www.goodreads.com', 'goodreads.com' );

	/**
	 * Builds a numeric widget id.
	 *
	 * Goodreads remembers the first query it sees for a widget id and then
	 * ignores later changes to shelf or book count. The id therefore has to
	 * change whenever those parameters change.
	 *
	 * @since 1.1.0
	 *
	 * @param string $shelf Valid shelf slug.
	 * @return string
	 */
	private static function widget_id( $shelf ) {
		$sort = self::shelf_sort( $shelf );
		$seed = $shelf . '|' . $sort . '|' . GOODREADS_RSS_WIDGET_MAX_COUNT . '|v1';
		$hash = sprintf( '%u', crc32( $seed ) );

		return (string) ( 100000000 + ( (int) $hash % 800000000 ) );
	}

	/**
	 * Chooses the widget sort for a shelf.
	 *
	 * @since 1.1.0
	 *
	 * @param string $shelf Valid shelf slug.
	 * @return string
	 */
	private static function shelf_sort( $shelf ) {
		return ( $shelf === 'read' ) ? 'date_read' : 'date_added';
	}

	/**
	 * Plugin activation callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function on_activation() {
		self::delete_all_transients();
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function on_deactivation() {
		self::delete_all_transients();
	}

	/**
	 * Reads the saved profile, falling back to a legacy RSS URL.
	 *
	 * The legacy URL is only used to recover the user id and shelf.
	 * Its secret key is never requested.
	 *
	 * @since 1.1.0
	 *
	 * @return array{user_id:string,shelf:string}
	 */
	public static function resolve_settings() {
		$user_id      = self::sanitize_user_id( (string) get_option( self::OPT_USER_ID, '' ) );
		$stored_shelf = get_option( self::OPT_SHELF, null );
		$shelf        = is_string( $stored_shelf ) ? self::sanitize_shelf( $stored_shelf ) : '';

		if ( $user_id === '' ) {
			$legacy  = self::parse_profile_input( (string) get_option( self::OPT_LEGACY_FEED_URL, '' ) );
			$user_id = $legacy['id'];
			if ( $shelf === '' && $legacy['shelf'] !== '' ) {
				$shelf = $legacy['shelf'];
			}
		}

		if ( $shelf === '' ) {
			$shelf = 'read';
		}

		return array(
			'user_id' => $user_id,
			'shelf'   => $shelf,
		);
	}

	/**
	 * Accepts a numeric id, a profile URL, or a legacy RSS URL.
	 *
	 * @since 1.1.0
	 *
	 * @param string $value Raw admin input.
	 * @return array{id:string,shelf:string}
	 */
	public static function parse_profile_input( $value ) {
		$result = array(
			'id'    => '',
			'shelf' => '',
		);
		$value  = trim( (string) $value );
		if ( $value === '' ) {
			return $result;
		}

		if ( preg_match( '/^[1-9]\d{0,19}$/', $value ) ) {
			$result['id'] = $value;
			return $result;
		}

		$value = esc_url_raw( $value );
		if ( $value === '' ) {
			return $result;
		}

		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) ) {
			return $result;
		}

		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( ! in_array( $host, self::ALLOWED_HOSTS, true ) ) {
			return $result;
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		if ( $scheme !== 'https' ) {
			return $result;
		}

		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		if ( preg_match( '#/(?:user/show|review/list_rss|review/list|review/custom_widget|review/grid_widget)/(\d+)#', $path, $matches ) ) {
			$result['id'] = $matches[1];
		}

		if ( ! empty( $parts['query'] ) ) {
			$query = array();
			parse_str( (string) $parts['query'], $query );
			if ( ! empty( $query['shelf'] ) && is_string( $query['shelf'] ) ) {
				$result['shelf'] = self::sanitize_shelf( $query['shelf'] );
			}
		}

		return $result;
	}

	/**
	 * Reduces profile input to a numeric Goodreads user id.
	 *
	 * @since 1.1.0
	 *
	 * @param string $value Raw user id or Goodreads URL.
	 * @return string
	 */
	public static function sanitize_user_id( $value ) {
		$parsed = self::parse_profile_input( $value );
		return $parsed['id'];
	}

	/**
	 * Normalizes a Goodreads shelf slug.
	 *
	 * @since 1.1.0
	 *
	 * @param string $shelf    Raw shelf slug.
	 * @param string $fallback Value used when the slug is empty or invalid.
	 * @return string
	 */
	public static function sanitize_shelf( $shelf, $fallback = '' ) {
		$shelf = strtolower( trim( (string) $shelf ) );
		$shelf = preg_replace( '/\s+/', '-', $shelf );
		if ( ! is_string( $shelf ) || ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,49}$/', $shelf ) ) {
			return $fallback;
		}

		return $shelf;
	}

	/**
	 * Returns books for a public Goodreads shelf.
	 *
	 * A transport or parse failure returns null so the caller can tell it
	 * apart from a shelf that simply has no books. Failures are not cached.
	 *
	 * @since 1.1.0
	 *
	 * @param string $user_id Numeric Goodreads user id.
	 * @param string $shelf   Shelf slug, for example read or currently-reading.
	 * @param int    $count   Maximum number of books to return.
	 * @return array<int, array{title:string,author:string,year:int,rating:float,cover:?string,link:string}>|null
	 */
	public static function get_books( $user_id, $shelf, $count ) {
		$user_id = self::sanitize_user_id( $user_id );
		$shelf   = self::sanitize_shelf( $shelf );
		$count   = self::sanitize_count( $count );
		if ( $user_id === '' || $shelf === '' ) {
			return array();
		}

		$cache_key = self::TRANSIENT_PREFIX . md5( $user_id . '|' . $shelf );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return array_slice( $cached, 0, $count );
		}

		$books = self::fetch_books( $user_id, $shelf );
		if ( ! is_array( $books ) ) {
			return null;
		}

		$ttl = empty( $books ) ? HOUR_IN_SECONDS : GOODREADS_RSS_WIDGET_CACHE_SECONDS;
		set_transient( $cache_key, $books, $ttl );

		return array_slice( $books, 0, $count );
	}

	/**
	 * Clamps book count to plugin limits.
	 *
	 * @since 1.0.0
	 *
	 * @param int $count Requested book count.
	 * @return int
	 */
	private static function sanitize_count( $count ) {
		$count = absint( $count );
		if ( $count < GOODREADS_RSS_WIDGET_MIN_COUNT ) {
			return GOODREADS_RSS_WIDGET_MIN_COUNT;
		}
		if ( $count > GOODREADS_RSS_WIDGET_MAX_COUNT ) {
			return GOODREADS_RSS_WIDGET_MAX_COUNT;
		}
		return $count;
	}

	/**
	 * Downloads and parses the official Goodreads shelf widget.
	 *
	 * @since 1.1.0
	 *
	 * @param string $user_id Numeric Goodreads user id.
	 * @param string $shelf   Valid shelf slug.
	 * @return array<int, array{title:string,author:string,year:int,rating:float,cover:?string,link:string}>|null
	 */
	private static function fetch_books( $user_id, $shelf ) {
		$url      = self::widget_url( $user_id, $shelf );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 2,
				'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return null;
		}

		return self::parse_widget_response( (string) wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Builds the shelf widget URL for a public profile.
	 *
	 * @since 1.1.0
	 *
	 * @param string $user_id Numeric Goodreads user id.
	 * @param string $shelf   Valid shelf slug.
	 * @return string
	 */
	private static function widget_url( $user_id, $shelf ) {
		$query = array(
			'num_books'    => GOODREADS_RSS_WIDGET_MAX_COUNT,
			'order'        => 'd',
			'shelf'        => $shelf,
			'show_author'  => 1,
			'show_cover'   => 1,
			'show_rating'  => 1,
			'show_review'  => 0,
			'show_title'   => 1,
			'sort'         => self::shelf_sort( $shelf ),
			'widget_id'    => self::widget_id( $shelf ),
		);

		return 'https://www.goodreads.com/review/custom_widget/' . $user_id . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Parses the JavaScript payload returned by the shelf widget.
	 *
	 * The publication year is not part of this payload, so it is stored as 0
	 * and the template omits it.
	 *
	 * @since 1.1.0
	 *
	 * @param string $body Raw widget response.
	 * @return array<int, array{title:string,author:string,year:int,rating:float,cover:?string,link:string}>|null
	 */
	private static function parse_widget_response( $body ) {
		if ( ! preg_match( "/var widget_code = '(.*)'\\s*;?\\s*var widget_div/s", $body, $matches ) ) {
			return null;
		}

		$html = stripcslashes( $matches[1] );
		if ( $html === '' ) {
			return null;
		}

		$previous = libxml_use_internal_errors( true );
		$document = new DOMDocument();
		$loaded   = $document->loadHTML( '<?xml encoding="utf-8">' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return null;
		}

		$xpath = new DOMXPath( $document );
		$nodes = $xpath->query( '//div[contains(concat(" ", normalize-space(@class), " "), " gr_custom_each_container_")]' );
		if ( ! $nodes instanceof DOMNodeList ) {
			return null;
		}

		$books = array();
		foreach ( $nodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}

			$title = self::node_text( $xpath, $node, './/div[contains(concat(" ", normalize-space(@class), " "), " gr_custom_title_")]//a' );
			if ( $title === '' ) {
				continue;
			}

			$author = self::node_text( $xpath, $node, './/div[contains(concat(" ", normalize-space(@class), " "), " gr_custom_author_")]//a' );
			$cover  = self::normalize_cover( self::node_attribute( $xpath, $node, './/div[contains(concat(" ", normalize-space(@class), " "), " gr_custom_book_container_")]//img', 'src' ) );
			$link   = self::book_link( $cover, self::node_attribute( $xpath, $node, './/div[contains(concat(" ", normalize-space(@class), " "), " gr_custom_title_")]//a', 'href' ) );
			$stars  = $xpath->query( './/img[contains(@src, "gr_flat_red_star_active")]', $node );

			$books[] = array(
				'title'  => $title,
				'author' => $author,
				'year'   => 0,
				'rating' => ( $stars instanceof DOMNodeList ) ? (float) min( 5, $stars->length ) : 0.0,
				'cover'  => $cover,
				'link'   => $link,
			);
		}

		return $books;
	}

	/**
	 * Reads trimmed text from the first node matched by an XPath query.
	 *
	 * @since 1.1.0
	 *
	 * @param DOMXPath   $xpath XPath bound to the widget document.
	 * @param DOMElement $node  Book container.
	 * @param string     $query Relative XPath.
	 * @return string
	 */
	private static function node_text( $xpath, $node, $query ) {
		$found = $xpath->query( $query, $node );
		if ( ! $found instanceof DOMNodeList || $found->length < 1 ) {
			return '';
		}

		$text = html_entity_decode( (string) $found->item( 0 )->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', trim( $text ) );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * Reads an attribute from the first node matched by an XPath query.
	 *
	 * @since 1.1.0
	 *
	 * @param DOMXPath   $xpath     XPath bound to the widget document.
	 * @param DOMElement $node      Book container.
	 * @param string     $query     Relative XPath.
	 * @param string     $attribute Attribute name.
	 * @return string
	 */
	private static function node_attribute( $xpath, $node, $query, $attribute ) {
		$found = $xpath->query( $query, $node );
		if ( ! $found instanceof DOMNodeList || $found->length < 1 || ! $found->item( 0 ) instanceof DOMElement ) {
			return '';
		}

		return trim( (string) $found->item( 0 )->getAttribute( $attribute ) );
	}

	/**
	 * Keeps a Goodreads cover URL and asks for a larger size.
	 *
	 * @since 1.1.0
	 *
	 * @param string $src Raw image URL.
	 * @return string|null
	 */
	private static function normalize_cover( $src ) {
		$src = trim( html_entity_decode( (string) $src, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( strpos( $src, '//' ) === 0 ) {
			$src = 'https:' . $src;
		}
		if ( strpos( $src, 'https://' ) !== 0 ) {
			return null;
		}

		$host = strtolower( (string) wp_parse_url( $src, PHP_URL_HOST ) );
		if ( ! in_array( $host, array( 'i.gr-assets.com', 'images.gr-assets.com', 's.gr-assets.com' ), true ) ) {
			return null;
		}

		$src = preg_replace( '/\._S[XY]\d+_\./', '._SX200_.', $src );
		return is_string( $src ) ? $src : null;
	}

	/**
	 * Builds a public book URL from the cover, or keeps a Goodreads review link.
	 *
	 * @since 1.1.0
	 *
	 * @param string|null $cover Cover URL, already normalized.
	 * @param string      $href  Title link from the widget.
	 * @return string
	 */
	private static function book_link( $cover, $href ) {
		if ( is_string( $cover ) && preg_match( '#/(\d+)\._S[XY]\d+_\.#', $cover, $matches ) ) {
			return 'https://www.goodreads.com/book/show/' . $matches[1];
		}

		$href = trim( html_entity_decode( (string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$parts = wp_parse_url( $href );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		if ( ! in_array( $host, self::ALLOWED_HOSTS, true ) || ! preg_match( '#^/(?:book/show|review/show)/\d+#', $path ) ) {
			return '';
		}

		return 'https://www.goodreads.com' . $path;
	}

	/**
	 * Deletes all plugin feed transients from the options table.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function delete_all_transients() {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return;
		}

		$option_like  = $wpdb->esc_like( '_transient_' . self::TRANSIENT_PREFIX ) . '%';
		$timeout_like = $wpdb->esc_like( '_transient_timeout_' . self::TRANSIENT_PREFIX ) . '%';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$option_like
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$timeout_like
			)
		);
	}
}
