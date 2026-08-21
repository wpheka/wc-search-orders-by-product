<?php
/**
 * The shared WPHEKA admin parent menu.
 *
 * @package WPHEKA\Framework
 */

declare( strict_types=1 );

namespace WPHEKA\Framework\V1\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates the one `WPHEKA` top-level menu several plugins share.
 *
 * **An adapter, not a menu system** (ADR-028). WordPress already registers
 * menus; what it has no concept of is a parent shared between independently
 * released plugins, and therefore no answer to "who creates it and who merely
 * attaches". That coordination is all this class owns. Screens, tabs, rendering
 * and page callbacks stay with the plugin.
 *
 * **The slug and label are a compatibility surface, not an implementation
 * detail.** Five plugins carry a hand-rolled copy of this logic, and only
 * plugins that have adopted the framework can use this class instead — so the
 * two mechanisms coexist and must agree. They do, because both use the slug
 * `wpheka_plugin_panel` and the label `WPHEKA`: whichever runs first creates the
 * parent, the rest attach, and neither needs to know the other exists. Changing
 * either string breaks every plugin that has not adopted the framework yet.
 *
 * The three filter names are part of that same surface, for the same reason.
 */
final class Menu {

	/**
	 * Slug of the shared parent.
	 *
	 * @var string
	 */
	public const PARENT_SLUG = 'wpheka_plugin_panel';

	/**
	 * Label of the shared parent.
	 *
	 * Deliberately not translated: it is a brand, not a string. Every
	 * hand-rolled copy carries the same note.
	 *
	 * @var string
	 */
	public const PARENT_LABEL = 'WPHEKA';

	/**
	 * Icon for the parent, when this plugin is the one that creates it.
	 *
	 * @var string
	 */
	private string $icon;

	/**
	 * Construct the adapter.
	 *
	 * @param string $icon Icon URL, or a dashicons- name. The framework ships no
	 *                     assets, so the caller supplies this; whoever registers
	 *                     the parent first decides what it looks like.
	 */
	public function __construct( string $icon = 'dashicons-info' ) {
		$this->icon = '' === $icon ? 'dashicons-info' : $icon;
	}

	/**
	 * The capability the shared parent uses.
	 *
	 * Computed from the filter rather than remembered, because the parent may
	 * have been registered by a plugin that has not adopted the framework. Every
	 * copy of this logic reads the same filter, so the answer matches whatever
	 * they used without this class having to observe it.
	 *
	 * @return string
	 */
	public function capability(): string {
		$capability = apply_filters( 'wpheka_plugin_panel_menu_page_capability', 'manage_options' );

		return is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
	}

	/**
	 * Register the shared parent if nothing has registered it yet.
	 *
	 * Call from `admin_menu`. Safe to call from every plugin on the site: the
	 * first wins and the rest are no-ops.
	 *
	 * @return bool Whether the parent exists after this call.
	 */
	public function ensure_parent(): bool {
		global $admin_page_hooks;

		if ( isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			return true;
		}

		if ( ! (bool) apply_filters( 'wpheka_plugin_panel_menu_page_show', true ) ) {
			return false;
		}

		$position = apply_filters( 'wpheka_plugins_menu_item_position', '55.5' );

		/*
		 * The empty callback is deliberate. The parent is a container, and
		 * WordPress points its link at the first submenu. An empty string rather
		 * than null, because null reaches string parameters inside core and is
		 * deprecated from PHP 8.1.
		 */
		add_menu_page(
			self::PARENT_LABEL,
			self::PARENT_LABEL,
			$this->capability(),
			self::PARENT_SLUG,
			'',
			$this->icon,
			$position
		);

		/*
		 * WordPress mirrors a parent as its own first submenu, so the menu shows
		 * "WPHEKA" twice: once as the top-level item and once beneath it. The
		 * mirror exists so a parent with its own page stays reachable; this
		 * parent has no page, so it is pure noise.
		 *
		 * Removed at PHP_INT_MAX rather than here, because the mirror is created
		 * by the first add_submenu_page() call and other plugins may still be
		 * adding pages after this one. Registering a callback from inside
		 * admin_menu is fine -- WordPress runs callbacks added during a hook so
		 * long as their priority has not already passed.
		 *
		 * Every hand-rolled copy of this logic carries the same removal, under a
		 * comment calling it a hack. It belongs here instead, once.
		 */
		add_action(
			'admin_menu',
			static function (): void {
				remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
			},
			PHP_INT_MAX
		);

		return true;
	}

	/**
	 * Attach a page under the shared parent.
	 *
	 * The capability defaults to the **parent's**, which is the point of routing
	 * this through one place. A submenu registered with a stricter capability
	 * than its parent is unreachable for users who can see the parent; a looser
	 * one is a hole. Hand-rolled copies filtered the parent's capability and then
	 * hardcoded `manage_options` on their own submenu, so the two disagreed the
	 * moment anyone used the filter.
	 *
	 * @param string   $page_title Already-translated browser title.
	 * @param string   $menu_title Already-translated menu label.
	 * @param string   $slug       Page slug.
	 * @param callable $callback   Renders the page.
	 * @param string   $capability Override. Defaults to the parent's.
	 * @return string|false The hook suffix, or false when the page was not added.
	 */
	public function add_page( string $page_title, string $menu_title, string $slug, callable $callback, string $capability = '' ) {
		if ( ! $this->ensure_parent() ) {
			return false;
		}

		return add_submenu_page(
			self::PARENT_SLUG,
			$page_title,
			$menu_title,
			'' === $capability ? $this->capability() : $capability,
			$slug,
			$callback
		);
	}
}
