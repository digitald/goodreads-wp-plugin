<?php
/**
 * Plugin Name:       Goodreads RSS Widget
 * Plugin URI:        https://github.com/digitald/Goodreads_wp_plugin
 * Description:       Mostra i libri da uno scaffale Goodreads tramite feed RSS con titolo, autore, anno, copertina e valutazione.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            digitald
 * Author URI:        https://github.com/digitald
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       goodreads-rss-widget
 * Domain Path:       /languages
 *
 * @package Goodreads_RSS_Widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plugin version. */
define( 'GOODREADS_RSS_WIDGET_VERSION', '1.0.0' );

/** Absolute path to the main plugin file. */
define( 'GOODREADS_RSS_WIDGET_FILE', __FILE__ );

/** Public URL to the plugin directory. */
define( 'GOODREADS_RSS_WIDGET_URL', plugin_dir_url( __FILE__ ) );

/** Absolute path to the plugin directory. */
define( 'GOODREADS_RSS_WIDGET_PATH', plugin_dir_path( __FILE__ ) );

/** Minimum number of books per shortcode request. */
define( 'GOODREADS_RSS_WIDGET_MIN_COUNT', 1 );

/** Maximum number of books per shortcode request. */
define( 'GOODREADS_RSS_WIDGET_MAX_COUNT', 50 );

/** Feed cache lifetime in seconds. */
define( 'GOODREADS_RSS_WIDGET_CACHE_SECONDS', HOUR_IN_SECONDS );

require_once GOODREADS_RSS_WIDGET_PATH . 'includes/class-feed.php';
require_once GOODREADS_RSS_WIDGET_PATH . 'includes/class-shortcode.php';

register_activation_hook( GOODREADS_RSS_WIDGET_FILE, 'goodreads_rss_widget_activate' );
register_deactivation_hook( GOODREADS_RSS_WIDGET_FILE, 'goodreads_rss_widget_deactivate' );

add_action( 'plugins_loaded', 'goodreads_rss_widget_bootstrap' );

/**
 * Checks minimum WordPress and PHP requirements.
 *
 * @since 1.0.0
 * @return bool True when requirements are met.
 */
function goodreads_rss_widget_requirements_met() {
	global $wp_version;

	return version_compare( PHP_VERSION, '7.4', '>=' )
		&& isset( $wp_version )
		&& version_compare( $wp_version, '5.8', '>=' );
}

/**
 * Displays an admin notice when plugin requirements are not met.
 *
 * @since 1.0.0
 * @return void
 */
function goodreads_rss_widget_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__(
		'Goodreads RSS Widget richiede WordPress 5.8 o superiore e PHP 7.4 o superiore.',
		'goodreads-rss-widget'
	);
	echo '</p></div>';
}

/**
 * Plugin activation callback.
 *
 * @since 1.0.0
 * @return void
 */
function goodreads_rss_widget_activate() {
	if ( ! goodreads_rss_widget_requirements_met() ) {
		deactivate_plugins( plugin_basename( GOODREADS_RSS_WIDGET_FILE ) );
		wp_die(
			esc_html__(
				'Goodreads RSS Widget non può essere attivato: aggiorna WordPress o PHP per soddisfare i requisiti minimi.',
				'goodreads-rss-widget'
			)
		);
	}

	Goodreads_Feed::on_activation();
}

/**
 * Plugin deactivation callback.
 *
 * @since 1.0.0
 * @return void
 */
function goodreads_rss_widget_deactivate() {
	Goodreads_Feed::on_deactivation();
}

/**
 * Bootstraps plugin hooks after requirements are verified.
 *
 * @since 1.0.0
 * @return void
 */
function goodreads_rss_widget_bootstrap() {
	if ( ! goodreads_rss_widget_requirements_met() ) {
		add_action( 'admin_notices', 'goodreads_rss_widget_requirements_notice' );
		return;
	}

	load_plugin_textdomain(
		'goodreads-rss-widget',
		false,
		dirname( plugin_basename( GOODREADS_RSS_WIDGET_FILE ) ) . '/languages'
	);

	add_action( 'init', array( 'Goodreads_Shortcode', 'register' ) );
	add_action( 'admin_menu', array( 'Goodreads_Admin', 'add_options_page' ) );
	add_action( 'admin_init', array( 'Goodreads_Admin', 'register_settings' ) );
}

/**
 * Admin settings page and default options.
 *
 * @since 1.0.0
 */
class Goodreads_Admin {

	/** @var string Option name for the Goodreads RSS feed URL. */
	const OPT_FEED_URL = 'goodreads_feed_url';

	/** @var string Option name for the default book count. */
	const OPT_COUNT = 'goodreads_default_count';

	/**
	 * Registers the settings page under Settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_options_page() {
		add_options_page(
			__( 'Goodreads', 'goodreads-rss-widget' ),
			__( 'Goodreads', 'goodreads-rss-widget' ),
			'manage_options',
			'goodreads-rss-widget',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Registers plugin options and settings fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'goodreads_rss_widget',
			self::OPT_FEED_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_feed_url' ),
				'default'           => '',
			)
		);

		register_setting(
			'goodreads_rss_widget',
			self::OPT_COUNT,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_count' ),
				'default'           => 10,
			)
		);

		add_settings_section(
			'goodreads_rss_widget_main',
			__( 'Impostazioni predefinite', 'goodreads-rss-widget' ),
			array( __CLASS__, 'render_settings_section' ),
			'goodreads-rss-widget'
		);

		add_settings_field(
			self::OPT_FEED_URL,
			__( 'URL feed RSS', 'goodreads-rss-widget' ),
			array( __CLASS__, 'render_feed_url_field' ),
			'goodreads-rss-widget',
			'goodreads_rss_widget_main'
		);

		add_settings_field(
			self::OPT_COUNT,
			__( 'Numero libri', 'goodreads-rss-widget' ),
			array( __CLASS__, 'render_count_field' ),
			'goodreads-rss-widget',
			'goodreads_rss_widget_main'
		);
	}

	/**
	 * Renders the intro text for the settings section.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_settings_section() {
		echo '<p>' . esc_html__(
			'Incolla l\'URL RSS completo del tuo scaffale Goodreads. L\'URL contiene una chiave privata: non condividerlo pubblicamente.',
			'goodreads-rss-widget'
		) . '</p>';
	}

	/**
	 * Renders the plugin settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$feed_url = (string) get_option( self::OPT_FEED_URL, '' );
		$count    = (int) get_option( self::OPT_COUNT, 10 );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Goodreads RSS Widget', 'goodreads-rss-widget' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'goodreads_rss_widget' );
				do_settings_sections( 'goodreads-rss-widget' );
				submit_button();
				?>
			</form>
			<?php self::render_help( $feed_url, $count ); ?>
		</div>
		<?php
	}

	/**
	 * Renders inline usage help on the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $feed_url Saved feed URL.
	 * @param int    $count    Saved default book count.
	 * @return void
	 */
	private static function render_help( $feed_url, $count ) {
		$count = max( GOODREADS_RSS_WIDGET_MIN_COUNT, min( GOODREADS_RSS_WIDGET_MAX_COUNT, $count ) );

		$shortcode_default = '[goodreads]';
		$shortcode_count   = '[goodreads count="' . $count . '"]';

		?>
		<div class="card" style="max-width: 720px; margin-top: 20px; padding: 16px 20px;">
			<h2 style="margin-top: 0;"><?php esc_html_e( 'Come usarlo', 'goodreads-rss-widget' ); ?></h2>
			<p><?php esc_html_e( 'Inserisci uno shortcode in una pagina, un articolo o un widget per mostrare i libri dal tuo scaffale Goodreads.', 'goodreads-rss-widget' ); ?></p>

			<h3><?php esc_html_e( 'Dove trovare l\'URL RSS', 'goodreads-rss-widget' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Accedi a Goodreads e vai su My Books.', 'goodreads-rss-widget' ); ?></li>
				<li><?php esc_html_e( 'Apri lo scaffale che vuoi mostrare (es. currently-reading, read, to-read).', 'goodreads-rss-widget' ); ?></li>
				<li><?php esc_html_e( 'In basso a sinistra clicca su RSS.', 'goodreads-rss-widget' ); ?></li>
				<li><?php esc_html_e( 'Copia l\'URL completo e incollalo nel campo sopra.', 'goodreads-rss-widget' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Shortcode', 'goodreads-rss-widget' ); ?></h3>
			<p><?php esc_html_e( 'Usa i valori salvati sopra:', 'goodreads-rss-widget' ); ?></p>
			<p><code><?php echo esc_html( $shortcode_default ); ?></code></p>

			<p><?php esc_html_e( 'Sovrascrivi solo il numero di libri:', 'goodreads-rss-widget' ); ?></p>
			<p><code><?php echo esc_html( $shortcode_count ); ?></code></p>

			<p><em><?php esc_html_e( 'Per sicurezza, lo shortcode non accetta l\'URL RSS come attributo.', 'goodreads-rss-widget' ); ?></em></p>

			<h3><?php esc_html_e( 'Widget (sidebar / footer)', 'goodreads-rss-widget' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Vai in Aspetto → Widget (o Personalizza → Widget).', 'goodreads-rss-widget' ); ?></li>
				<li><?php esc_html_e( 'Aggiungi un blocco Shortcode.', 'goodreads-rss-widget' ); ?></li>
				<li><?php esc_html_e( 'Incolla lo shortcode e salva.', 'goodreads-rss-widget' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Limiti e compatibilità', 'goodreads-rss-widget' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Richiede un URL RSS Goodreads valido con parametro key.', 'goodreads-rss-widget' ); ?></li>
				<li><?php printf( esc_html__( 'Numero libri visualizzabili: da %1$d a %2$d per shortcode.', 'goodreads-rss-widget' ), GOODREADS_RSS_WIDGET_MIN_COUNT, GOODREADS_RSS_WIDGET_MAX_COUNT ); ?></li>
				<li><?php esc_html_e( 'Le stelline mostrano solo la tua valutazione (user_rating); se è 0, non vengono mostrate.', 'goodreads-rss-widget' ); ?></li>
				<li><?php esc_html_e( 'Cache del feed: 1 ora.', 'goodreads-rss-widget' ); ?></li>
				<li><?php esc_html_e( 'Compatibile con WordPress 5.8+ e PHP 7.4+.', 'goodreads-rss-widget' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Sicurezza', 'goodreads-rss-widget' ); ?></h3>
			<p><?php esc_html_e( 'L\'URL RSS contiene una chiave segreta. Non inserirlo in pagine pubbliche, shortcode o widget visibili agli editori non fidati.', 'goodreads-rss-widget' ); ?></p>
			<?php if ( Goodreads_Feed::validate_feed_url( $feed_url ) !== '' ) : ?>
				<p><?php esc_html_e( 'Feed configurato (chiave mascherata):', 'goodreads-rss-widget' ); ?></p>
				<p><code><?php echo esc_html( Goodreads_Feed::redact_feed_url( $feed_url ) ); ?></code></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Sanitizes the Goodreads RSS feed URL option.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public static function sanitize_feed_url( $value ) {
		return Goodreads_Feed::validate_feed_url( (string) $value );
	}

	/**
	 * Sanitizes the default book count option.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw option value.
	 * @return int
	 */
	public static function sanitize_count( $value ) {
		$value = absint( $value );
		if ( $value < GOODREADS_RSS_WIDGET_MIN_COUNT ) {
			return GOODREADS_RSS_WIDGET_MIN_COUNT;
		}
		if ( $value > GOODREADS_RSS_WIDGET_MAX_COUNT ) {
			return GOODREADS_RSS_WIDGET_MAX_COUNT;
		}
		return $value;
	}

	/**
	 * Renders the feed URL settings field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_feed_url_field() {
		$value = (string) get_option( self::OPT_FEED_URL, '' );
		printf(
			'<input type="url" name="%1$s" value="%2$s" class="large-text code" placeholder="%3$s" autocomplete="off" />',
			esc_attr( self::OPT_FEED_URL ),
			esc_attr( $value ),
			esc_attr__( 'https://www.goodreads.com/review/list_rss/...', 'goodreads-rss-widget' )
		);
	}

	/**
	 * Renders the book count settings field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_count_field() {
		$value = (int) get_option( self::OPT_COUNT, 10 );
		printf(
			'<input type="number" name="%1$s" value="%2$d" min="%3$d" max="%4$d" />',
			esc_attr( self::OPT_COUNT ),
			$value,
			GOODREADS_RSS_WIDGET_MIN_COUNT,
			GOODREADS_RSS_WIDGET_MAX_COUNT
		);
	}
}
