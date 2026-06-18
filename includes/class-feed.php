<?php
/**
 * Fetches and parses the Goodreads bookshelf RSS feed.
 *
 * @package Goodreads_RSS_Widget
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles feed URL validation, remote retrieval, caching, and parsing.
 *
 * @since 1.0.0
 */
class Goodreads_Feed {

	/** @var string Prefix for WordPress transient cache keys. */
	private const TRANSIENT_PREFIX = 'goodreads_feed_';

	/** @var string Allowed Goodreads hostnames. */
	private const ALLOWED_HOSTS = array( 'www.goodreads.com', 'goodreads.com' );

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
	 * Validates a Goodreads bookshelf RSS feed URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Raw feed URL.
	 * @return string Sanitized URL, or empty string when invalid.
	 */
	public static function validate_feed_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ) );
		if ( $url === '' ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( ! in_array( $host, self::ALLOWED_HOSTS, true ) ) {
			return '';
		}

		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		if ( ! preg_match( '#^/review/list_rss/\d+/?$#', $path ) ) {
			return '';
		}

		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $query );
		}

		if ( empty( $query['key'] ) || ! is_string( $query['key'] ) ) {
			return '';
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'https';
		if ( $scheme !== 'https' ) {
			return '';
		}

		return $url;
	}

	/**
	 * Returns parsed books from a Goodreads RSS feed URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $feed_url Valid Goodreads RSS feed URL.
	 * @param int    $count    Maximum number of books to return.
	 * @return array<int, array{title:string,author:string,year:int,rating:float,cover:?string,link:string}>
	 */
	public static function get_books( $feed_url, $count ) {
		$feed_url = self::validate_feed_url( $feed_url );
		if ( $feed_url === '' ) {
			return array();
		}

		$count     = self::sanitize_count( $count );
		$cache_key = self::TRANSIENT_PREFIX . md5( $feed_url );

		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return array_slice( $cached, 0, $count );
		}

		$books = self::fetch_books( $feed_url );
		set_transient( $cache_key, $books, GOODREADS_RSS_WIDGET_CACHE_SECONDS );

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
	 * Fetches books from the Goodreads RSS feed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $feed_url Valid feed URL.
	 * @return array<int, array{title:string,author:string,year:int,rating:float,cover:?string,link:string}>
	 */
	private static function fetch_books( $feed_url ) {
		require_once ABSPATH . WPINC . '/feed.php';

		$feed = fetch_feed( $feed_url );
		if ( is_wp_error( $feed ) ) {
			return array();
		}

		$maxitems = $feed->get_item_quantity();
		if ( ! is_int( $maxitems ) || $maxitems < 1 ) {
			$maxitems = 10;
		}

		$items = $feed->get_items( 0, $maxitems );
		if ( empty( $items ) ) {
			return array();
		}

		$out = array();
		foreach ( $items as $item ) {
			$title       = method_exists( $item, 'get_title' ) ? (string) $item->get_title() : '';
			$link        = method_exists( $item, 'get_link' ) ? (string) $item->get_link() : '';
			$description = method_exists( $item, 'get_description' ) ? (string) $item->get_description() : '';

			$author = self::get_item_tag( $item, 'author_name' );
			$year   = self::get_item_tag( $item, 'book_published' );
			$rating = self::get_item_tag( $item, 'user_rating' );
			$cover  = self::get_item_tag( $item, 'book_medium_image_url' );

			if ( $cover === '' ) {
				$cover = self::get_item_tag( $item, 'book_image_url' );
			}
			if ( $cover === '' ) {
				$cover = self::extract_cover_src( $description );
			}
			if ( $author === '' ) {
				$author = self::extract_description_field( $description, 'author' );
			}

			$out[] = array(
				'title'  => html_entity_decode( trim( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'author' => html_entity_decode( trim( $author ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'year'   => absint( $year ),
				'rating' => self::parse_rating( $rating ),
				'cover'  => $cover !== '' ? $cover : null,
				'link'   => $link,
			);
		}

		return $out;
	}

	/**
	 * Reads a child tag from a Goodreads RSS item.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item SimplePie feed item.
	 * @param string $tag  Tag name.
	 * @return string
	 */
	private static function get_item_tag( $item, $tag ) {
		if ( ! method_exists( $item, 'get_item_tags' ) ) {
			return '';
		}

		$namespaces = array( '', 'http://backend.userland.com/rss2' );
		foreach ( $namespaces as $namespace ) {
			$tags = $item->get_item_tags( $namespace, $tag );
			if ( empty( $tags ) || ! is_array( $tags ) ) {
				continue;
			}

			$first = $tags[0];
			if ( is_array( $first ) && isset( $first['data'] ) ) {
				return trim( (string) $first['data'] );
			}
			if ( is_string( $first ) ) {
				return trim( $first );
			}
		}

		return '';
	}

	/**
	 * Normalizes a Goodreads user rating.
	 *
	 * @since 1.0.0
	 *
	 * @param string $rating Raw rating value.
	 * @return float
	 */
	private static function parse_rating( $rating ) {
		$rating = floatval( str_replace( ',', '.', trim( (string) $rating ) ) );
		if ( $rating <= 0 ) {
			return 0.0;
		}
		if ( $rating > 5 ) {
			return 5.0;
		}
		return $rating;
	}

	/**
	 * Extracts a cover image URL from the RSS description HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $description RSS item description.
	 * @return string
	 */
	private static function extract_cover_src( $description ) {
		if ( $description === '' ) {
			return '';
		}

		if ( preg_match( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $description, $matches ) ) {
			return trim( (string) $matches[1] );
		}

		return '';
	}

	/**
	 * Extracts a labeled field from the Goodreads description block.
	 *
	 * @since 1.0.0
	 *
	 * @param string $description RSS item description.
	 * @param string $field       Field label, e.g. "author".
	 * @return string
	 */
	private static function extract_description_field( $description, $field ) {
		if ( $description === '' ) {
			return '';
		}

		$pattern = '/\b' . preg_quote( $field, '/' ) . ':\s*([^<]+)/i';
		if ( preg_match( $pattern, $description, $matches ) ) {
			return trim( html_entity_decode( (string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}

		return '';
	}

	/**
	 * Returns a feed URL with the secret key redacted for display.
	 *
	 * @since 1.0.0
	 *
	 * @param string $feed_url Valid feed URL.
	 * @return string
	 */
	public static function redact_feed_url( $feed_url ) {
		$feed_url = self::validate_feed_url( $feed_url );
		if ( $feed_url === '' ) {
			return '';
		}

		$parts = wp_parse_url( $feed_url );
		if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
			return $feed_url;
		}

		$query = array();
		parse_str( (string) $parts['query'], $query );
		if ( isset( $query['key'] ) ) {
			$query['key'] = '***';
		}

		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		$host   = isset( $parts['host'] ) ? $parts['host'] : 'www.goodreads.com';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '';

		return $scheme . '://' . $host . $path . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
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
