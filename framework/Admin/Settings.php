<?php
/**
 * A plugin's settings, declared once.
 *
 * @package WPHEKA\Framework
 */

declare( strict_types=1 );

namespace WPHEKA\Framework\V1\Admin;

use WPHEKA\Framework\V1\Core\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Sections of fields, declared in PHP and rendered by whichever surface applies.
 *
 * This is the public API of the Admin module (ADR-021). The React components
 * are an implementation detail; the schema is the contract. Plugin authors
 * write field definitions, not markup, which is what actually makes settings
 * consistent across plugins — a shared component library still allows two
 * developers to assemble different screens.
 *
 * Renderers consume this: a React shell for standalone plugins, `WC_Settings_API`
 * for WooCommerce gateways (which do not own their settings screen), and REST
 * for reads and writes.
 */
final class Settings {

	/**
	 * Stand-in sent to a screen in place of a stored PASSWORD value.
	 *
	 * A settings screen needs to show *that* a secret is set; it never needs the
	 * secret itself. Anything sent instead of this lands in the page source of an
	 * admin screen, where it survives in browser caches, "view source", and any
	 * screenshot a customer attaches to a support ticket.
	 *
	 * A customer whose secret is literally this string cannot re-save that exact
	 * value, which is the accepted cost of the round-trip being unambiguous.
	 */
	public const MASKED = '********';

	/**
	 * Sections, keyed by id, each with a title and its fields.
	 *
	 * @var array<string, array{title: string, description: string, fields: Field[]}>
	 */
	private array $sections = array();

	/**
	 * Section currently receiving fields.
	 *
	 * @var string
	 */
	private string $current = '';

	/**
	 * Open a section. Subsequent add() calls attach to it.
	 *
	 * @param string $id          Section key.
	 * @param string $title       Already-translated title (ADR-019).
	 * @param string $description Already-translated description.
	 * @return self
	 */
	public function section( string $id, string $title, string $description = '' ): self {
		$this->sections[ $id ] = array(
			'title'       => $title,
			'description' => $description,
			'fields'      => array(),
		);

		$this->current = $id;

		return $this;
	}

	/**
	 * Add a field to the open section.
	 *
	 * @param Field $field Field to add.
	 * @return self
	 * @throws \LogicException When no section has been opened yet.
	 */
	public function add( Field $field ): self {
		if ( '' === $this->current ) {
			throw new \LogicException( 'WPHEKA Framework: call section() before add().' );
		}

		$this->sections[ $this->current ]['fields'][] = $field;

		return $this;
	}

	/**
	 * Every declared field, flattened across sections.
	 *
	 * @return Field[]
	 */
	public function fields(): array {
		$fields = array();

		foreach ( $this->sections as $section ) {
			foreach ( $section['fields'] as $field ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Defaults for every declared field.
	 *
	 * Feed this straight to `Core\Options`, so the schema is the single source
	 * of truth for both what a setting means and what it starts as.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		$defaults = array();

		foreach ( $this->fields() as $field ) {
			$defaults[ $field->id() ] = $field->default_value();
		}

		return $defaults;
	}

	/**
	 * Sanitise a submitted payload against the schema.
	 *
	 * Keys that are not declared fields are **dropped, not passed through**.
	 * A settings endpoint that stores arbitrary keys is a way to write
	 * unexpected options, so the schema is treated as an allowlist.
	 *
	 * Absent keys are left absent rather than reset to their default, so a
	 * partial update — one section of a screen, or one toggle — does not wipe
	 * settings the caller never mentioned.
	 *
	 * A PASSWORD field submitted as `MASKED` is treated the same way: that is the
	 * screen handing back what `bootstrap()` gave it, which means the user did not
	 * touch the field. Storing it would overwrite a real licence key or API secret
	 * with eight asterisks on any save of the screen it sits on.
	 *
	 * @param array<string, mixed> $input Raw submitted values.
	 * @return array<string, mixed> Values safe to persist.
	 */
	public function sanitize( array $input ): array {
		$clean = array();

		foreach ( $this->fields() as $field ) {
			if ( ! array_key_exists( $field->id(), $input ) ) {
				continue;
			}

			$value = $input[ $field->id() ];

			if ( Field::PASSWORD === $field->type() && is_string( $value ) && self::MASKED === $value ) {
				continue;
			}

			$clean[ $field->id() ] = $field->sanitize( $value );
		}

		return $clean;
	}

	/**
	 * The whole schema as data, for REST and the React renderer.
	 *
	 * Carries no values — only the shape. Values come from `Core\Options`, so a
	 * schema response can be cached while values cannot.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function to_array(): array {
		$out = array();

		foreach ( $this->sections as $id => $section ) {
			$fields = array();

			foreach ( $section['fields'] as $field ) {
				$fields[] = $field->to_array();
			}

			$out[] = array(
				'id'          => $id,
				'title'       => $section['title'],
				'description' => $section['description'],
				'fields'      => $fields,
			);
		}

		return $out;
	}

	/**
	 * Schema plus current values, ready to bootstrap a settings screen.
	 *
	 * One payload rather than two requests: a settings screen needs both, and
	 * the first paint should not wait on a round-trip (ADR-021).
	 *
	 * **Values are masked, not raw.** This payload is inlined into an admin page
	 * by `Screen::enqueue()` and returned by settings REST reads, so a PASSWORD
	 * field's value — a licence key, an API secret — would otherwise be readable
	 * in the page source of any screen that renders it. `Core\Logger::redact()`
	 * already covers logs and diagnostics reports; this is the same class of
	 * problem on the other surface.
	 *
	 * @param Options $options Where values are stored.
	 * @return array<string, mixed>
	 */
	public function bootstrap( Options $options ): array {
		return array(
			'sections' => $this->to_array(),
			'values'   => $this->mask( $options->all() ),
			'scope'    => $options->scope(),
		);
	}

	/**
	 * Replace stored secrets with `MASKED`, leaving everything else alone.
	 *
	 * An unset secret stays empty rather than becoming a mask, so a screen can
	 * still tell "not configured" from "configured, not shown".
	 *
	 * @param array<string, mixed> $values Stored values.
	 * @return array<string, mixed>
	 */
	public function mask( array $values ): array {
		foreach ( $this->fields() as $field ) {
			$id = $field->id();

			if ( Field::PASSWORD !== $field->type() || ! array_key_exists( $id, $values ) ) {
				continue;
			}

			// Compared, not cast: a value that is somehow not a string must still
			// be masked rather than raising a conversion notice on the way.
			if ( '' !== $values[ $id ] && null !== $values[ $id ] ) {
				$values[ $id ] = self::MASKED;
			}
		}

		return $values;
	}
}
