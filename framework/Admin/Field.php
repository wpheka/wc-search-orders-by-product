<?php
/**
 * One declared setting.
 *
 * @package WPHEKA\Framework
 */

declare( strict_types=1 );

namespace WPHEKA\Framework\V1\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * A single setting, declared once and rendered by whichever surface needs it.
 *
 * The field describes *what* a setting is — its type, default, and how to make
 * a submitted value safe. It never describes how the control looks. That is the
 * renderer's job, and keeping the two apart is what lets one declaration drive
 * a React screen, a `WC_Settings_API` tab, and a REST payload (ADR-021).
 *
 * The type list is deliberately short: only what our existing plugins actually
 * use. Types can be added compatibly later; they cannot be removed.
 */
final class Field {

	public const TEXT     = 'text';
	public const TEXTAREA = 'textarea';
	public const NUMBER   = 'number';
	public const TOGGLE   = 'toggle';
	public const SELECT   = 'select';
	public const RADIO    = 'radio';
	public const PASSWORD = 'password';

	/**
	 * Setting key, unique within the plugin.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * One of the type constants.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Human label, already translated by the plugin (ADR-019).
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Longer explanation, already translated.
	 *
	 * @var string
	 */
	private string $description = '';

	/**
	 * Value used when the setting has never been saved.
	 *
	 * @var mixed
	 */
	private $default_value = '';

	/**
	 * Choices for select and radio, as value => already-translated label.
	 *
	 * @var array<string, string>
	 */
	private array $choices = array();

	/**
	 * Short status word shown beside the label, already translated.
	 *
	 * @var string
	 */
	private string $badge = '';

	/**
	 * Construct a field.
	 *
	 * @param string $id    Setting key.
	 * @param string $type  One of the type constants.
	 * @param string $label Already-translated label.
	 */
	public function __construct( string $id, string $type, string $label ) {
		$this->id    = $id;
		$this->type  = $type;
		$this->label = $label;
	}

	/**
	 * Set the description.
	 *
	 * @param string $description Already-translated text.
	 * @return self
	 */
	public function describe( string $description ): self {
		$this->description = $description;

		return $this;
	}

	/**
	 * Set the default value.
	 *
	 * @param mixed $value Default.
	 * @return self
	 */
	public function default_to( $value ): self {
		$this->default_value = $value;

		return $this;
	}

	/**
	 * Set the choices for a select or radio field.
	 *
	 * @param array<string, string> $choices value => already-translated label.
	 * @return self
	 */
	public function choices( array $choices ): self {
		$this->choices = $choices;

		return $this;
	}

	/**
	 * Set a badge.
	 *
	 * @param string $badge Already-translated word, e.g. "Beta".
	 * @return self
	 */
	public function badge( string $badge ): self {
		$this->badge = $badge;

		return $this;
	}

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Field type.
	 *
	 * @return string
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Default value.
	 *
	 * @return mixed
	 */
	public function default_value() {
		return $this->default_value;
	}

	/**
	 * Make a submitted value safe to store.
	 *
	 * Sanitisation is decided by the declared type rather than left to the
	 * caller. A settings screen is the most common route for untrusted input
	 * into a plugin, and "the caller will remember to sanitise" is how that
	 * goes wrong.
	 *
	 * A value outside a select or radio field's declared choices falls back to
	 * the default instead of being stored: the choices are the whole
	 * specification of what is valid, so anything else is either a bug or an
	 * attack.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return mixed Value safe to persist.
	 */
	public function sanitize( $value ) {
		/*
		 * Every branch below casts to string, and casting an array or an object
		 * emits a notice and stores "Array" — a REST client sending
		 * `{"api_key": {}}` is enough. There is no meaningful scalar reading of a
		 * structure, so the declared default is the honest answer.
		 *
		 * TOGGLE is exempt: its whole question is truthiness, and it never casts.
		 * null is not scalar and lands here too, which is correct — a submitted
		 * null is "no value", and that is what the default is for.
		 */
		if ( self::TOGGLE !== $this->type && ! is_scalar( $value ) ) {
			return $this->default_value;
		}

		switch ( $this->type ) {
			case self::TOGGLE:
				return (bool) $value;

			case self::NUMBER:
				return is_numeric( $value ) ? 0 + $value : $this->default_value;

			case self::TEXTAREA:
				return sanitize_textarea_field( (string) $value );

			case self::SELECT:
			case self::RADIO:
				$candidate = sanitize_text_field( (string) $value );

				return array_key_exists( $candidate, $this->choices ) ? $candidate : $this->default_value;

			case self::PASSWORD:
				// Not escaped or trimmed: an API secret may legitimately contain
				// characters sanitize_text_field would strip. Stored as given,
				// redacted on output by Core\Logger::redact().
				return (string) $value;

			case self::TEXT:
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * The field as data, for REST and the React renderer.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'          => $this->id,
			'type'        => $this->type,
			'label'       => $this->label,
			'description' => $this->description,
			'default'     => $this->default_value,
			'choices'     => $this->choices,
			'badge'       => $this->badge,
		);
	}
}
