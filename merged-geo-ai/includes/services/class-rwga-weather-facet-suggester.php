<?php

/**

 * Heuristic and remote shopping-weather facet suggestions from product catalog text.

 *

 * @package ReactWoo_Geo_AI

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



/**

 * Local keyword scoring and optional remote workflow for product weather facet tagging.

 */

class RWGA_Weather_Facet_Suggester {



	/**

	 * Remote workflow key on api.reactwoo.com.

	 */

	const REMOTE_WORKFLOW_KEY = 'weather_facet_suggest';



	/**

	 * @var array<string, string[]>

	 */

	private static $keyword_map = array(

		'wet'     => array( 'rain', 'raincoat', 'umbrella', 'waterproof', 'poncho', 'wellington', 'gumboot', 'puddle', 'wet' ),

		'cold'    => array( 'winter', 'thermal', 'fleece', 'coat', 'jacket', 'snow', 'cold', 'heated', 'wool', 'beanie' ),

		'hot'     => array( 'cooling', 'fan', 'summer', 'heat', 'hot', 'ice', 'hydration', 'shorts' ),

		'windy'   => array( 'wind', 'windbreaker', 'kite', 'gust' ),

		'sunny'   => array( 'sunglasses', 'sun hat', 'bright', 'sunshine', 'sunny' ),

		'high_uv' => array( 'spf', 'sunscreen', 'sun cream', 'uv', 'sun block', 'sunblock' ),

		'poor_air' => array( 'air purifier', 'hepa', 'pollution mask', 'n95', 'respirator', 'smog' ),

		'high_pollen' => array( 'antihistamine', 'hayfever', 'hay fever', 'allergy', 'pollen', 'nasal spray' ),

		'dry'     => array( 'desert', 'drought', 'dry bag' ),

		'mild'    => array( 'spring', 'lightweight', 'layer', 'transitional' ),

	);



	/**

	 * @param int $product_id Product ID.

	 * @return array{facets: string[], scores: array<string, int>, source: string, rationale?: string, error?: string}

	 */

	public static function suggest_for_product( $product_id ) {

		$pid = absint( $product_id );

		if ( $pid <= 0 || ! function_exists( 'wc_get_product' ) ) {

			return array(

				'facets' => array(),

				'scores' => array(),

				'source' => 'none',

			);

		}



		if ( class_exists( 'RWGA_Engine', false ) && class_exists( 'RWGA_Remote_Client', false ) && RWGA_Engine::should_try_remote() ) {

			$remote = self::suggest_via_remote( $pid );

			if ( ! is_wp_error( $remote ) && ! empty( $remote['facets'] ) ) {

				return $remote;

			}

			if ( 'remote' === RWGA_Engine::get_mode() && is_wp_error( $remote ) ) {

				return array(

					'facets' => array(),

					'scores' => array(),

					'source' => 'remote_error',

					'error'  => $remote->get_error_message(),

				);

			}

		}



		return self::suggest_via_keywords( $pid );

	}



	/**

	 * @param int $product_id Product ID.

	 * @return array{facets: string[], scores: array<string, int>, source: string, rationale?: string}|\WP_Error

	 */

	private static function suggest_via_remote( $product_id ) {

		$product = wc_get_product( $product_id );

		if ( ! $product ) {

			return new WP_Error( 'rwga_no_product', __( 'Product not found.', 'reactwoo-geo-ai' ) );

		}



		$allowed = self::allowed_facet_slugs();

		$payload = array(

			'product' => array(

				'id'                => $product_id,

				'name'              => $product->get_name(),

				'short_description' => wp_strip_all_tags( $product->get_short_description() ),

				'description'       => wp_strip_all_tags( $product->get_description() ),

				'categories'        => self::term_names_list( $product_id, 'product_cat' ),

				'tags'              => self::term_names_list( $product_id, 'product_tag' ),

			),

			'category_weather_facets' => self::get_product_category_facets( $product_id ),

			'allowed_facets'          => $allowed,

		);



		$dispatched = RWGA_Remote_Client::dispatch( self::REMOTE_WORKFLOW_KEY, $payload );

		if ( is_wp_error( $dispatched ) ) {

			return $dispatched;

		}



		$response = isset( $dispatched['engine_response'] ) && is_array( $dispatched['engine_response'] )

			? $dispatched['engine_response']

			: array();

		$facets   = isset( $response['facets'] ) && is_array( $response['facets'] ) ? $response['facets'] : array();

		$facets   = self::sanitize_facets( $facets, $allowed );

		$scores   = array();

		foreach ( $facets as $slug ) {

			$scores[ $slug ] = 1;

		}



		$out = array(

			'facets' => $facets,

			'scores' => $scores,

			'source' => 'remote',

		);

		if ( ! empty( $response['rationale'] ) && is_string( $response['rationale'] ) ) {

			$out['rationale'] = sanitize_text_field( $response['rationale'] );

		}

		return $out;

	}



	/**

	 * @param int $product_id Product ID.

	 * @return array{facets: string[], scores: array<string, int>, source: string}

	 */

	private static function suggest_via_keywords( $product_id ) {

		$product = wc_get_product( $product_id );

		if ( ! $product ) {

			return array(

				'facets' => array(),

				'scores' => array(),

				'source' => 'none',

			);

		}



		$haystack = strtolower(

			implode(

				' ',

				array_filter(

					array(

						$product->get_name(),

						wp_strip_all_tags( $product->get_short_description() ),

						wp_strip_all_tags( $product->get_description() ),

						self::term_names_for_product( $product_id, 'product_cat' ),

						self::term_names_for_product( $product_id, 'product_tag' ),

					)

				)

			)

		);



		$scores = array();

		foreach ( self::$keyword_map as $facet => $keywords ) {

			$score = 0;

			foreach ( $keywords as $word ) {

				if ( false !== strpos( $haystack, strtolower( $word ) ) ) {

					++$score;

				}

			}

			if ( $score > 0 ) {

				$scores[ $facet ] = $score;

			}

		}



		if ( empty( $scores ) ) {

			$category = self::get_product_category_facets( $product_id );

			if ( ! empty( $category ) ) {

				$scores = array_fill_keys( $category, 1 );

			}

		} else {

			$category = self::get_product_category_facets( $product_id );

			if ( ! empty( $category ) ) {

				foreach ( $category as $slug ) {

					if ( ! isset( $scores[ $slug ] ) ) {

						$scores[ $slug ] = 1;

					}

				}

			}

		}



		if ( empty( $scores ) ) {

			return array(

				'facets' => array(),

				'scores' => array(),

				'source' => 'keywords',

			);

		}



		arsort( $scores );

		$facets = self::sanitize_facets( array_keys( $scores ), self::allowed_facet_slugs() );



		/**

		 * Filter Geo AI weather facet suggestions for a product.

		 *

		 * @param string[]             $facets  Suggested facet slugs.

		 * @param array<string, int>   $scores  Match scores keyed by slug.

		 * @param int                  $pid     Product ID.

		 * @param string               $haystack Combined catalog text.

		 */

		$facets = apply_filters( 'rwga_weather_facet_suggestions', $facets, $scores, $product_id, $haystack );



		return array(

			'facets' => is_array( $facets ) ? array_values( $facets ) : array(),

			'scores' => $scores,

			'source' => 'keywords',

		);

	}



	/**

	 * @return string[]

	 */

	private static function allowed_facet_slugs() {

		if ( class_exists( 'RWGCP_Weather_Facets', false ) ) {

			return RWGCP_Weather_Facets::get_available_slugs();

		}

		if ( class_exists( 'RWGCM_Weather_Affinity', false ) ) {

			return array_column( RWGCM_Weather_Affinity::get_facet_definitions(), 'slug' );

		}

		return array_keys( self::$keyword_map );

	}



	/**

	 * @param string[] $facets  Raw slugs.

	 * @param string[] $allowed Allowed slugs.

	 * @return string[]

	 */

	private static function sanitize_facets( array $facets, array $allowed ) {

		if ( class_exists( 'RWGCM_Weather_Affinity', false ) ) {

			return RWGCM_Weather_Affinity::sanitize_facet_list( $facets );

		}

		if ( class_exists( 'RWGCP_Weather_Facets', false ) ) {

			return RWGCP_Weather_Facets::sanitize_facet_list( $facets );

		}

		$flip = array_flip( $allowed );

		$out  = array();

		foreach ( $facets as $facet ) {

			$s = strtolower( trim( (string) $facet ) );

			if ( isset( $flip[ $s ] ) ) {

				$out[] = $s;

			}

		}

		return array_values( array_unique( $out ) );

	}



	/**

	 * @param int    $product_id Product ID.

	 * @param string $taxonomy   Taxonomy slug.

	 * @return string[]

	 */

	private static function term_names_list( $product_id, $taxonomy ) {

		$terms = get_the_terms( $product_id, $taxonomy );

		if ( ! is_array( $terms ) ) {

			return array();

		}

		$names = array();

		foreach ( $terms as $term ) {

			if ( $term instanceof WP_Term ) {

				$names[] = $term->name;

			}

		}

		return $names;

	}



	/**

	 * @param int    $product_id Product ID.

	 * @param string $taxonomy   Taxonomy slug.

	 * @return string

	 */

	private static function term_names_for_product( $product_id, $taxonomy ) {

		return implode( ' ', self::term_names_list( $product_id, $taxonomy ) );

	}



	/**

	 * @param int $product_id Product ID.

	 * @return string[]

	 */

	private static function get_product_category_facets( $product_id ) {

		if ( class_exists( 'RWGCM_Weather_Affinity', false ) ) {

			return RWGCM_Weather_Affinity::get_product_category_facets( $product_id );

		}

		return array();

	}

}

