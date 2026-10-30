<?php
/**
 * The catalogue: works for sale.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Catalogue;

use Galerio\Core\Fields\Display;
use Galerio\Core\Fields\Field;
use Galerio\Core\Settings\Features;
use NumberFormatter;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * A work's availability, price, edition and dealer, for sites that sell.
 *
 * The catalogue is a switch on Settings → Galerio, off until a site turns it
 * on. While it's off the fields read as empty everywhere (pages, REST, block
 * bindings, Schema.org) and their Sales boxes leave the editor. Values are
 * kept, so turning it on again brings them back.
 *
 * While it's on:
 * - A work with an edition and none left available reads as sold.
 * - The price reads as empty unless the work is available or reserved, and
 *   while it's "on application", so it's never shown by mistake.
 *   `work.price_label` is the price as shown: the amount in its currency, or
 *   "Price on application".
 * - Available from falls back to the creator's Represented by, and reads as
 *   empty unless the work is available or reserved.
 * - Works in Query Loops have a class for their availability, and sold or
 *   reserved ones a marker on their card.
 * - The work's Schema.org data has an Offer and its inventory number.
 */
final class Catalogue {

	/**
	 * The catalogue's fields.
	 */
	const FIELDS = array(
		'work.availability',
		'work.price',
		'work.price_on_application',
		'work.price_label',
		'work.currency',
		'work.edition_size',
		'work.editions_available',
		'work.framed',
		'work.inventory_number',
		'work.available_from',
		'profile.represented_by',
	);

	/**
	 * The Pods group holding the fields on works and profiles.
	 */
	const GROUP = 'sales_group';

	/**
	 * Availabilities a work is for sale in.
	 */
	const FOR_SALE = array( 'available', 'reserved' );

	/**
	 * Availabilities that mark a work's card.
	 */
	const MARKED = array( 'sold', 'reserved' );

	/**
	 * The site currency when none is chosen.
	 */
	const DEFAULT_CURRENCY = 'GBP';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'galerio_field_value', array( __CLASS__, 'field_value' ), 10, 3 );
		add_filter( 'galerio_field_display', array( __CLASS__, 'field_display' ), 10, 4 );
		add_filter( 'pods_meta_groups_get', array( __CLASS__, 'editor_groups' ), 10, 3 );
		add_filter( 'post_class', array( __CLASS__, 'post_class' ), 10, 3 );
		add_filter( 'render_block_core/post-template', array( __CLASS__, 'markers' ) );
		add_filter( 'galerio_schema_data', array( __CLASS__, 'schema' ), 10, 2 );
	}

	/**
	 * Whether the catalogue is on. It's off until a site turns it on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$on = (bool) (int) get_option( Features::PREFIX . 'catalogue', 0 );

		/** This filter is documented in includes/settings/class-features.php */
		return (bool) apply_filters( 'galerio_feature_enabled', $on, 'catalogue' );
	}

	/**
	 * Currencies a site or work can use: ISO 4217 code => name.
	 *
	 * The Pods package offers the same list, so a code added here must be
	 * added to the currency fields there too.
	 *
	 * @return array<string, string>
	 */
	public static function currencies() {
		return array(
			'GBP' => __( 'Pound sterling (GBP)', 'galerio-core' ),
			'EUR' => __( 'Euro (EUR)', 'galerio-core' ),
			'USD' => __( 'US dollar (USD)', 'galerio-core' ),
			'CAD' => __( 'Canadian dollar (CAD)', 'galerio-core' ),
			'AUD' => __( 'Australian dollar (AUD)', 'galerio-core' ),
			'NZD' => __( 'New Zealand dollar (NZD)', 'galerio-core' ),
			'CHF' => __( 'Swiss franc (CHF)', 'galerio-core' ),
			'SEK' => __( 'Swedish krona (SEK)', 'galerio-core' ),
			'NOK' => __( 'Norwegian krone (NOK)', 'galerio-core' ),
			'DKK' => __( 'Danish krone (DKK)', 'galerio-core' ),
			'PLN' => __( 'Polish złoty (PLN)', 'galerio-core' ),
			'JPY' => __( 'Japanese yen (JPY)', 'galerio-core' ),
			'CNY' => __( 'Chinese yuan (CNY)', 'galerio-core' ),
			'HKD' => __( 'Hong Kong dollar (HKD)', 'galerio-core' ),
			'INR' => __( 'Indian rupee (INR)', 'galerio-core' ),
			'ZAR' => __( 'South African rand (ZAR)', 'galerio-core' ),
		);
	}

	/**
	 * The site's currency, from Settings → Galerio.
	 *
	 * @return string ISO 4217 code.
	 */
	public static function site_currency() {
		$code = (string) get_option( Features::PREFIX . 'currency', '' );

		return isset( self::currencies()[ $code ] ) ? $code : self::DEFAULT_CURRENCY;
	}

	/**
	 * A work's currency: its own, or else the site's.
	 *
	 * @param int $post_id Work.
	 * @return string ISO 4217 code.
	 */
	public static function currency( $post_id ) {
		$code = self::raw( 'work.currency', $post_id );

		return '' !== $code ? $code : self::site_currency();
	}

	/**
	 * A work's availability, with an edition that has none left reading as
	 * sold.
	 *
	 * @param int $post_id Work.
	 * @return string One of the availability field's options, or empty.
	 */
	public static function availability( $post_id ) {
		return self::enabled() ? (string) galerio_get_field( 'work.availability', $post_id ) : '';
	}

	/**
	 * Whether a work is for sale: available or reserved.
	 *
	 * @param int $post_id Work.
	 * @return bool
	 */
	public static function for_sale( $post_id ) {
		return in_array( self::availability( $post_id ), self::FOR_SALE, true );
	}

	/**
	 * Format an amount of money for the site's language.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency ISO 4217 code.
	 * @return string Plain text, such as "£1,250".
	 */
	public static function format_price( $amount, $currency ) {
		$whole = floor( $amount ) === (float) $amount;

		if ( class_exists( NumberFormatter::class ) ) {
			$formatter = new NumberFormatter( determine_locale(), NumberFormatter::CURRENCY );
			if ( $whole ) {
				$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, 0 );
			}
			$text = $formatter->formatCurrency( (float) $amount, $currency );

			if ( false !== $text ) {
				return $text;
			}
		}

		$symbols = array(
			'GBP' => '£',
			'EUR' => '€',
			'USD' => '$',
			'JPY' => '¥',
			'CNY' => '¥',
			'INR' => '₹',
		);
		$number  = number_format_i18n( $amount, $whole ? 0 : 2 );

		return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] . $number : $currency . ' ' . $number;
	}

	/**
	 * `work.price_label`: the price as shown.
	 *
	 * @param int $post_id Work.
	 * @return string Plain text: the amount in its currency, "Price on
	 *                application", or empty when the work isn't for sale or
	 *                has no price.
	 */
	public static function price_label( $post_id ) {
		if ( ! self::for_sale( $post_id ) ) {
			return '';
		}

		if ( self::raw( 'work.price_on_application', $post_id ) ) {
			return __( 'Price on application', 'galerio-core' );
		}

		$price = self::raw( 'work.price', $post_id );

		return null === $price ? '' : self::format_price( $price, self::currency( $post_id ) );
	}

	/**
	 * A field's value as stored, before this class or anything else changes
	 * it.
	 *
	 * @param string $key     Field key.
	 * @param int    $post_id Post.
	 * @return mixed
	 */
	private static function raw( $key, $post_id ) {
		$field = galerio_fields()->get( $key );

		return $field->normalise( galerio_fields()->adapter()->get( $field, $post_id ) );
	}

	/**
	 * The catalogue's fields read as empty while it's off, and as described
	 * on the class while it's on.
	 *
	 * @param mixed $value   Value.
	 * @param Field $field   Field.
	 * @param int   $post_id Post.
	 * @return mixed
	 */
	public static function field_value( $value, $field, $post_id = 0 ) {
		if ( ! in_array( $field->key, self::FIELDS, true ) ) {
			return $value;
		}

		if ( ! self::enabled() ) {
			return $field->empty_value();
		}

		switch ( $field->key ) {
			case 'work.availability':
				return 'available' === $value && self::sold_out( $post_id ) ? 'sold' : $value;

			case 'work.price':
				return self::for_sale( $post_id ) && ! self::raw( 'work.price_on_application', $post_id ) ? $value : $field->empty_value();

			case 'work.available_from':
				if ( ! self::for_sale( $post_id ) ) {
					return $field->empty_value();
				}
				return $value ? $value : self::represented_by( $post_id );
		}

		return $value;
	}

	/**
	 * Whether a work is in an edition that has none left available.
	 *
	 * @param int $post_id Work.
	 * @return bool
	 */
	private static function sold_out( $post_id ) {
		$size = self::raw( 'work.edition_size', $post_id );
		$left = self::raw( 'work.editions_available', $post_id );

		return $size > 0 && null !== $left && $left <= 0;
	}

	/**
	 * The Represented by link of a work's creator.
	 *
	 * @param int $post_id Work.
	 * @return array|null Link.
	 */
	private static function represented_by( $post_id ) {
		$creator = (int) galerio_get_field( 'work.creator', $post_id );

		return $creator && 'publish' === get_post_status( $creator ) ? galerio_get_field( 'profile.represented_by', $creator ) : null;
	}

	/**
	 * An edition shows as "Edition of 10, 3 available".
	 *
	 * @param string $html    Display HTML.
	 * @param Field  $field   Field.
	 * @param mixed  $raw     Value.
	 * @param int    $post_id Post.
	 * @return string
	 */
	public static function field_display( $html, $field, $raw, $post_id ) {
		if ( 'work.edition_size' !== $field->key || ! $raw ) {
			return $html;
		}

		/* translators: %s: how many copies an edition has, e.g. "10". */
		$text = sprintf( __( 'Edition of %s', 'galerio-core' ), number_format_i18n( $raw ) );
		$left = galerio_get_field( 'work.editions_available', $post_id );

		if ( null !== $left && $left > 0 && self::for_sale( $post_id ) ) {
			/* translators: 1: an edition, e.g. "Edition of 10". 2: how many copies are left, e.g. "3". */
			$text = sprintf( __( '%1$s, %2$s available', 'galerio-core' ), $text, number_format_i18n( $left ) );
		}

		return esc_html( $text );
	}

	/**
	 * While the catalogue is off, the editor leaves out the Sales boxes.
	 *
	 * @param array  $groups Pods field groups.
	 * @param string $type   Pod type.
	 * @param string $name   Pod name.
	 * @return array
	 */
	public static function editor_groups( $groups, $type, $name ) {
		if ( 'post_type' !== $type || ! in_array( $name, array( 'galerio_work', 'galerio_profile' ), true ) || self::enabled() ) {
			return $groups;
		}

		return array_values(
			array_filter(
				(array) $groups,
				function ( $group ) {
					return self::GROUP !== ( isset( $group['name'] ) ? $group['name'] : '' );
				}
			)
		);
	}

	/**
	 * A work's availability as a class, such as galerio-availability-sold.
	 *
	 * @param string[] $classes Classes.
	 * @param string[] $css     Classes asked for.
	 * @param int      $post_id Post.
	 * @return string[]
	 */
	public static function post_class( $classes, $css, $post_id ) {
		if ( 'galerio_work' === get_post_type( $post_id ) ) {
			$availability = self::availability( $post_id );

			if ( '' !== $availability ) {
				$classes[] = 'galerio-availability-' . str_replace( '_', '-', $availability );
			}
		}

		return $classes;
	}

	/**
	 * Works in a Query Loop that are sold or reserved get a marker on their
	 * card, such as "Sold".
	 *
	 * @param string $content Rendered Post Template.
	 * @return string
	 */
	public static function markers( $content ) {
		if ( ! self::enabled() || false === strpos( $content, 'galerio-availability-' ) ) {
			return $content;
		}

		$options = galerio_fields()->get( 'work.availability' )->options;
		$marked  = false;

		$content = preg_replace_callback(
			'/<li\b[^>]*\bclass="[^"]*\bgalerio-availability-(' . implode( '|', array_map( 'preg_quote', self::MARKED ) ) . ')\b[^"]*"[^>]*>/',
			function ( $tag ) use ( $options, &$marked ) {
				$marked = true;

				return $tag[0] . sprintf(
					'<span class="galerio-availability-marker galerio-availability-marker--%1$s">%2$s</span>',
					esc_attr( $tag[1] ),
					esc_html( $options[ $tag[1] ] )
				);
			},
			$content
		);

		if ( $marked ) {
			self::marker_styles();
		}

		return $content;
	}

	/**
	 * The markers' styles: a small label in the card's top corner. A theme
	 * can restyle .galerio-availability-marker.
	 */
	private static function marker_styles() {
		if ( wp_style_is( 'galerio-catalogue' ) ) {
			return;
		}

		wp_register_style( 'galerio-catalogue', false, array(), GALERIO_CORE_VERSION );
		wp_enqueue_style( 'galerio-catalogue' );
		wp_add_inline_style(
			'galerio-catalogue',
			':where(.wp-block-post:has(> .galerio-availability-marker)){position:relative;}
			:where(.galerio-availability-marker){position:absolute;z-index:1;top:0.5rem;left:0.5rem;padding:0.15em 0.5em;font-size:0.75rem;line-height:1.4;letter-spacing:0.05em;text-transform:uppercase;background-color:var(--wp--preset--color--contrast,#000);color:var(--wp--preset--color--base,#fff);pointer-events:none;}'
		);
	}

	/**
	 * Filters a works query can use: value => label.
	 *
	 * @return array<string, string>
	 */
	public static function filters() {
		return array(
			'available' => __( 'Available', 'galerio-core' ),
			'sold'      => __( 'Sold', 'galerio-core' ),
		);
	}

	/**
	 * Query arguments for works with an availability, counting an edition
	 * with none left as sold, as the field does.
	 *
	 * @param string $filter One of filters().
	 * @return array A meta_query, or nothing for an unknown filter or while
	 *               the catalogue is off.
	 */
	public static function query_args( $filter ) {
		if ( ! self::enabled() || ! isset( self::filters()[ $filter ] ) ) {
			return array();
		}

		$key = function ( $name ) {
			return galerio_fields()->get( "work.$name" )->source;
		};
		$is  = function ( $availability ) use ( $key ) {
			return array(
				'key'   => $key( 'availability' ),
				'value' => $availability,
			);
		};

		$size = $key( 'edition_size' );
		$left = $key( 'editions_available' );

		// An edition is sold out when it has a size, and a count of those left that is 0.
		$sold_out = array(
			'relation' => 'AND',
			array(
				'key'     => $size,
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => $left,
				'value'   => '',
				'compare' => '!=',
			),
			array(
				'key'     => $left,
				'value'   => 0,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			),
		);

		if ( 'sold' === $filter ) {
			$meta = array(
				'relation' => 'OR',
				$is( 'sold' ),
				array(
					'relation' => 'AND',
					$is( 'available' ),
					$sold_out,
				),
			);
		} else {
			$meta = array(
				'relation' => 'AND',
				$is( 'available' ),
				array(
					'relation' => 'OR',
					array(
						'key'     => $size,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => $size,
						'value'   => 0,
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => $left,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => $left,
						'value' => '',
					),
					array(
						'key'     => $left,
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			);
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtering by availability is the point.
		return array( 'meta_query' => array( $meta ) );
	}

	/**
	 * A work's Schema.org data gets its inventory number and, with a price
	 * shown, an Offer.
	 *
	 * @param array|null $data Data.
	 * @param WP_Post    $post Post.
	 * @return array|null
	 */
	public static function schema( $data, $post ) {
		if ( ! $data || 'galerio_work' !== $post->post_type || ! self::enabled() ) {
			return $data;
		}

		$number = galerio_get_field( 'work.inventory_number', $post );
		if ( '' !== $number ) {
			$data['identifier'] = $number;
		}

		$price = galerio_get_field( 'work.price', $post );
		if ( null === $price ) {
			return $data;
		}

		$dealer = galerio_get_field( 'work.available_from', $post );
		$offer  = array(
			'@type'         => 'Offer',
			'price'         => $price,
			'priceCurrency' => self::currency( $post->ID ),
			'availability'  => 'reserved' === self::availability( $post->ID ) ? 'https://schema.org/Reserved' : 'https://schema.org/InStock',
			'url'           => $dealer && ! $dealer['offline'] ? $dealer['url'] : get_permalink( $post ),
		);

		if ( $dealer ) {
			$offer['seller'] = array(
				'@type' => 'Organization',
				'name'  => Display::link_text( $dealer ),
				'url'   => $dealer['offline'] ? null : $dealer['url'],
			);
		}

		$data['offers'] = $offer;

		return $data;
	}
}
