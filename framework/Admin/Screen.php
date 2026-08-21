<?php
/**
 * Registers a standalone admin screen for a settings schema.
 *
 * @package WPHEKA\Framework
 */

declare( strict_types=1 );

namespace WPHEKA\Framework\V1\Admin;

use WPHEKA\Framework\V1\Core\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a menu page and hands the settings schema to the front end.
 *
 * The PHP side of a settings screen is deliberately thin: it registers a menu
 * entry, renders a mount point, and passes the schema and current values as one
 * bootstrap payload. Everything else is the renderer's job.
 *
 * The bootstrap payload matters more than it looks. A settings screen needs both
 * the schema and the values, and fetching them over REST after first paint means
 * an empty screen while the request completes. Passing them inline removes that
 * round-trip; REST is still used for saving, and for re-reading after a save
 * (ADR-021).
 *
 * WooCommerce gateways do not use this class — they render through
 * `WooCommerceFields` into WooCommerce's own screens instead.
 */
final class Screen {

	/**
	 * Menu slug and asset handle base.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Already-translated page title (ADR-019).
	 *
	 * @var string
	 */
	private string $title;

	/**
	 * Capability required to see the screen.
	 *
	 * @var string
	 */
	private string $capability;

	/**
	 * The declared schema.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Where values live.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * REST namespace the front end should talk to.
	 *
	 * @var string
	 */
	private string $rest_namespace = '';

	/**
	 * Absolute URL of the built front-end script, if any.
	 *
	 * @var string
	 */
	private string $script_url = '';

	/**
	 * Version string used to cache-bust the script.
	 *
	 * @var string
	 */
	private string $script_version = '';

	/**
	 * Message shown when a user without the capability reaches the page by URL.
	 *
	 * English by default, because framework code carries no text domain
	 * (ADR-019). A plugin supplies a translated string in its own domain.
	 *
	 * @var string
	 */
	private string $denied_message = 'You do not have permission to access this page.';

	/**
	 * Construct a screen.
	 *
	 * @param string   $slug       Menu slug.
	 * @param string   $title      Already-translated page title.
	 * @param string   $capability Capability required to view.
	 * @param Settings $settings   Declared schema.
	 * @param Options  $options    Value store.
	 */
	public function __construct( string $slug, string $title, string $capability, Settings $settings, Options $options ) {
		$this->slug       = sanitize_key( $slug );
		$this->title      = $title;
		$this->capability = $capability;
		$this->settings   = $settings;
		$this->options    = $options;
	}

	/**
	 * Set the access-denied message, already translated by the plugin.
	 *
	 * @param string $message Already-translated message.
	 * @return self
	 */
	public function denied_message( string $message ): self {
		$this->denied_message = $message;

		return $this;
	}

	/**
	 * Point the front end at a REST namespace and a built script.
	 *
	 * @param string $rest_namespace REST namespace, e.g. "my-plugin/v1".
	 * @param string $script_url     Absolute URL of the built script.
	 * @param string $version        Version string for cache busting.
	 * @return self
	 */
	public function with_app( string $rest_namespace, string $script_url, string $version ): self {
		$this->rest_namespace = $rest_namespace;
		$this->script_url     = $script_url;
		$this->script_version = $version;

		return $this;
	}

	/**
	 * Hook menu registration.
	 *
	 * Must be called on `init` or later, never at plugin include time: building
	 * a schema translates its labels, and translating before `init` fails
	 * silently on WordPress 6.7+ (ADR-007).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Add the menu page.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$hook = add_menu_page(
			$this->title,
			$this->title,
			$this->capability,
			$this->slug,
			array( $this, 'render' )
		);

		if ( '' !== $hook ) {
			// Enqueue on this screen only. A settings bundle loaded across the
			// whole admin is how plugins earn a reputation for slowing it down.
			add_action( 'admin_print_scripts-' . $hook, array( $this, 'enqueue' ) );
		}
	}

	/**
	 * Enqueue the front-end app and its bootstrap data.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( '' === $this->script_url ) {
			return;
		}

		$handle = 'wpheka-' . $this->slug;

		wp_enqueue_script( $handle, $this->script_url, array( 'wp-element' ), $this->script_version, true );

		wp_add_inline_script(
			$handle,
			sprintf(
				'window.wphekaSettings = window.wphekaSettings || {}; window.wphekaSettings[%s] = %s;',
				wp_json_encode( $this->slug ),
				wp_json_encode( $this->bootstrap() )
			),
			'before'
		);
	}

	/**
	 * Render the mount point.
	 *
	 * The capability is re-checked here. `add_menu_page` hides the entry from
	 * users without it, but hiding a menu item is not access control — the page
	 * is still reachable by URL.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html( $this->denied_message ) );
		}

		printf( '<div class="wrap"><div id="%s"></div></div>', esc_attr( 'wpheka-' . $this->slug ) );
	}

	/**
	 * Everything the front end needs to render without a round-trip.
	 *
	 * Inlined into the admin page by `enqueue()`, so what it carries is readable
	 * in the page source. `Settings::bootstrap()` masks PASSWORD values for that
	 * reason, and `Settings::sanitize()` treats the mask coming back as "not
	 * changed" so a save cannot overwrite the real secret with it.
	 *
	 * @return array<string, mixed>
	 */
	public function bootstrap(): array {
		return array_merge(
			$this->settings->bootstrap( $this->options ),
			array(
				'slug'     => $this->slug,
				'mount'    => 'wpheka-' . $this->slug,
				'restBase' => esc_url_raw( rest_url( $this->rest_namespace ) ),
				// The REST nonce, without which every save from the admin screen
				// is rejected as an unauthenticated request.
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
