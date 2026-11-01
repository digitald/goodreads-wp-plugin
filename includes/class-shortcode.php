<?php
/**
 * Registers and renders the [goodreads] shortcode.
 *
 * @package Goodreads_RSS_Widget
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode output for the Goodreads book grid.
 *
 * @since 1.0.0
 */
class Goodreads_Shortcode {

	/** @var string Option name for the default book count. */
	const OPT_COUNT = 'goodreads_default_count';

	/**
	 * Registers plugin shortcodes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'goodreads', array( __CLASS__, 'render' ) );
	}

	/**
	 * Renders the [goodreads] shortcode.
	 *
	 * Supported attributes:
	 * - count: number of books to display (1-50).
	 * - shelf: shelf slug, overriding the admin setting.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'count' => '',
				'shelf' => '',
			),
			(array) $atts,
			'goodreads'
		);

		$settings = Goodreads_Feed::resolve_settings();
		$count    = isset( $atts['count'] ) ? $atts['count'] : '';
		$shelf    = Goodreads_Feed::sanitize_shelf( isset( $atts['shelf'] ) ? (string) $atts['shelf'] : '' );
		if ( $shelf === '' ) {
			$shelf = $settings['shelf'];
		}

		if ( $count === '' || $count === null ) {
			$count = (int) get_option( self::OPT_COUNT, 10 );
		}

		$count = absint( $count );
		if ( $count < GOODREADS_RSS_WIDGET_MIN_COUNT ) {
			$count = 10;
		}
		if ( $count > GOODREADS_RSS_WIDGET_MAX_COUNT ) {
			$count = GOODREADS_RSS_WIDGET_MAX_COUNT;
		}

		if ( ! wp_style_is( 'goodreads-rss-widget', 'enqueued' ) ) {
			wp_enqueue_style(
				'goodreads-rss-widget',
				GOODREADS_RSS_WIDGET_URL . 'assets/style.css',
				array(),
				GOODREADS_RSS_WIDGET_VERSION
			);
		}

		if ( $settings['user_id'] === '' ) {
			return '<div class="goodreads-error">' . esc_html__( 'Indica l\'ID del profilo Goodreads nelle impostazioni del plugin.', 'goodreads-rss-widget' ) . '</div>';
		}

		$books = Goodreads_Feed::get_books( $settings['user_id'], $shelf, $count );
		if ( ! is_array( $books ) ) {
			return '<div class="goodreads-error">' . esc_html__( 'Scaffale Goodreads non disponibile.', 'goodreads-rss-widget' ) . '</div>';
		}
		if ( $books === array() ) {
			return '<div class="goodreads-error">' . esc_html__( 'Nessun libro su questo scaffale.', 'goodreads-rss-widget' ) . '</div>';
		}

		ob_start();
		?>
		<div class="goodreads-grid" aria-label="<?php echo esc_attr__( 'Libri da Goodreads', 'goodreads-rss-widget' ); ?>">
			<?php foreach ( $books as $book ) : ?>
				<?php
				$title  = isset( $book['title'] ) ? (string) $book['title'] : '';
				$author = isset( $book['author'] ) ? (string) $book['author'] : '';
				$year   = isset( $book['year'] ) ? (int) $book['year'] : 0;
				$rating = isset( $book['rating'] ) ? (float) $book['rating'] : 0.0;
				$cover  = isset( $book['cover'] ) ? $book['cover'] : null;
				$link   = isset( $book['link'] ) ? (string) $book['link'] : '';

				$star_str = self::render_stars( $rating );
				$alt_text = $title;
				if ( $author !== '' ) {
					$alt_text .= ' - ' . $author;
				}
				if ( $year > 0 ) {
					$alt_text .= ' (' . (string) $year . ')';
				}
				?>
				<article class="goodreads-item">
					<a class="goodreads-cover-link" href="<?php echo esc_url( $link ); ?>">
						<?php if ( ! empty( $cover ) ) : ?>
							<img
								class="goodreads-cover"
								src="<?php echo esc_url( (string) $cover ); ?>"
								alt="<?php echo esc_attr( $alt_text ); ?>"
								loading="lazy"
								width="98"
								height="147"
							/>
						<?php endif; ?>
					</a>
					<h3 class="goodreads-title">
						<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
					</h3>
					<?php if ( $author !== '' ) : ?>
						<p class="goodreads-author"><?php echo esc_html( $author ); ?></p>
					<?php endif; ?>
					<?php if ( $year > 0 ) : ?>
						<p class="goodreads-year"><?php echo esc_html( (string) $year ); ?></p>
					<?php endif; ?>
					<?php if ( $rating > 0 ) : ?>
						<div class="goodreads-rating" role="img" aria-label="<?php echo esc_attr( self::render_rating_aria( $rating ) ); ?>">
							<?php echo esc_html( $star_str ); ?>
						</div>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Builds an accessible rating label.
	 *
	 * @since 1.0.0
	 *
	 * @param float $rating Numeric rating.
	 * @return string
	 */
	private static function render_rating_aria( $rating ) {
		$rating = max( 0, min( 5, (float) $rating ) );
		return sprintf(
			/* translators: %s: star rating out of 5, e.g. 4 */
			__( '%s su 5 stelle', 'goodreads-rss-widget' ),
			number_format( $rating, 0 )
		);
	}

	/**
	 * Converts a numeric rating into star characters.
	 *
	 * Goodreads user ratings use whole stars only.
	 *
	 * @since 1.0.0
	 *
	 * @param float $rating Numeric rating from 1 to 5.
	 * @return string
	 */
	private static function render_stars( $rating ) {
		if ( $rating <= 0 ) {
			return '';
		}

		$full_stars = (int) round( (float) $rating );
		if ( $full_stars < 1 ) {
			return '';
		}
		if ( $full_stars > 5 ) {
			$full_stars = 5;
		}

		$out = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$out .= ( $i <= $full_stars ) ? '★' : '☆';
		}

		return $out;
	}
}
