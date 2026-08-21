<?php
/**
 * Renders a settings schema into WooCommerce's settings format.
 *
 * @package WPHEKA\Framework
 */

declare( strict_types=1 );

namespace WPHEKA\Framework\V1\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Converts a `Settings` schema into the `form_fields` array `WC_Settings_API` expects.
 *
 * A WooCommerce payment gateway does not own its settings screen: its fields
 * are rendered by WooCommerce, inside WooCommerce's tabs, from a `form_fields`
 * property. That is why the framework's settings layer is a schema with
 * per-surface renderers rather than a React component library (ADR-021) — a
 * component library could never reach this surface at all, and gateways are the
 * commercial products.
 *
 * Pure PHP, no build step, no JavaScript. A gateway takes this renderer and
 * nothing else from the Admin module.
 */
final class WooCommerceFields {

	/**
	 * Map a schema field type to the closest WooCommerce field type.
	 *
	 * `radio` is deliberately mapped to `select`: `WC_Settings_API` has no radio
	 * type, and inventing one by injecting markup into a `title` field is the
	 * kind of trick that breaks on a WooCommerce upgrade. A select carries the
	 * same choice semantics and is rendered by WooCommerce itself.
	 *
	 * @var array<string, string>
	 */
	private const TYPE_MAP = array(
		Field::TEXT     => 'text',
		Field::TEXTAREA => 'textarea',
		Field::NUMBER   => 'number',
		Field::TOGGLE   => 'checkbox',
		Field::SELECT   => 'select',
		Field::RADIO    => 'select',
		Field::PASSWORD => 'password',
	);

	/**
	 * Build the `form_fields` array for a gateway or settings page.
	 *
	 * Section titles become WooCommerce `title` rows, so the grouping declared
	 * in the schema survives into WooCommerce's screen instead of collapsing
	 * into one flat list.
	 *
	 * @param Settings $settings Declared schema.
	 * @return array<string, array<string, mixed>>
	 */
	public static function from( Settings $settings ): array {
		$out = array();

		foreach ( $settings->to_array() as $section ) {
			if ( '' !== $section['title'] ) {
				$out[ $section['id'] . '_title' ] = array(
					'title'       => $section['title'],
					'type'        => 'title',
					'description' => $section['description'],
				);
			}

			foreach ( $section['fields'] as $field ) {
				$out[ $field['id'] ] = self::field( $field );
			}
		}

		return $out;
	}

	/**
	 * Convert one field.
	 *
	 * @param array<string, mixed> $field Field as data, from Field::to_array().
	 * @return array<string, mixed>
	 */
	private static function field( array $field ): array {
		$type = (string) ( $field['type'] ?? Field::TEXT );

		$out = array(
			'title'       => $field['label'],
			'type'        => self::TYPE_MAP[ $type ] ?? 'text',
			'description' => $field['description'],
			'default'     => $field['default'],
			// WooCommerce shows the description as a hover tip by default, which
			// hides it. Settings descriptions are usually load-bearing, so they
			// are shown inline instead.
			'desc_tip'    => false,
		);

		/*
		 * A badge has no WooCommerce equivalent. Rather than drop it, it is
		 * prefixed to the label in brackets: losing the styling is acceptable,
		 * losing the information is not.
		 */
		if ( '' !== (string) ( $field['badge'] ?? '' ) ) {
			$out['title'] = sprintf( '%s [%s]', $field['label'], $field['badge'] );
		}

		if ( in_array( $type, array( Field::SELECT, Field::RADIO ), true ) ) {
			$out['options'] = $field['choices'];
		}

		if ( Field::TOGGLE === $type ) {
			// WooCommerce checkboxes store 'yes'/'no', not booleans. The label
			// sits beside the box, so the title would otherwise render twice.
			$out['label']   = $field['label'];
			$out['default'] = $field['default'] ? 'yes' : 'no';
		}

		return $out;
	}

	/**
	 * Normalise values read back from WooCommerce into schema-native types.
	 *
	 * `WC_Settings_API` stores checkboxes as the strings 'yes' and 'no'. Code
	 * reading a toggle should get a boolean regardless of which surface saved
	 * it, or every consumer ends up writing its own comparison and one of them
	 * gets it wrong.
	 *
	 * @param Settings             $settings Declared schema.
	 * @param array<string, mixed> $values   Values as WooCommerce stored them.
	 * @return array<string, mixed>
	 */
	public static function normalise( Settings $settings, array $values ): array {
		foreach ( $settings->fields() as $field ) {
			$id = $field->id();

			if ( Field::TOGGLE === $field->type() && array_key_exists( $id, $values ) ) {
				$values[ $id ] = in_array( $values[ $id ], array( 'yes', true, 1, '1' ), true );
			}
		}

		return $values;
	}
}
