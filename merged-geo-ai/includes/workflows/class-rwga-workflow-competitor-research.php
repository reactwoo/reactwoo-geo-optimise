<?php
/**
 * Competitor research workflow (local bounded stub).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores a structured competitor snapshot for review (no external fetch in stub).
 */
class RWGA_Workflow_Competitor_Research extends RWGA_Workflow_Base {

	/**
	 * @return string
	 */
	public function get_key() {
		return 'competitor_research';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return __( 'Competitor research', 'reactwoo-geo-ai' );
	}

	/**
	 * @return string
	 */
	public function get_agent_key() {
		return 'market_analyst';
	}

	/**
	 * @param array<string, mixed> $input Raw input.
	 * @return true|\WP_Error
	 */
	public function validate_input( array $input ) {
		$g = $this->gate_capabilities();
		if ( is_wp_error( $g ) ) {
			return $g;
		}
		$url = isset( $input['competitor_url'] ) ? esc_url_raw( (string) $input['competitor_url'] ) : '';
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error(
				'rwga_need_competitor_url',
				__( 'Provide a valid competitor_url (http or https).', 'reactwoo-geo-ai' )
			);
		}
		$pid = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		if ( $pid > 0 && ! get_post( $pid ) instanceof WP_Post ) {
			return new WP_Error( 'rwga_page_missing', __( 'Page not found.', 'reactwoo-geo-ai' ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public function build_request_payload( array $input ) {
		$geo = isset( $input['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $input['geo_target'] ), 0, 2 ) ) : '';
		return array(
			'page_id'         => isset( $input['page_id'] ) ? (int) $input['page_id'] : 0,
			'competitor_url'  => isset( $input['competitor_url'] ) ? esc_url_raw( (string) $input['competitor_url'] ) : '',
			'page_type'       => isset( $input['page_type'] ) ? sanitize_key( (string) $input['page_type'] ) : 'page',
			'geo_target'      => '' !== $geo ? $geo : '',
		);
	}

	/**
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute( array $input ) {
		$v = $this->validate_input( $input );
		if ( is_wp_error( $v ) ) {
			return $v;
		}
		$in = $this->build_request_payload( $input );

		if ( ! class_exists( 'RWGA_Generation_Router', false ) ) {
			return new WP_Error( 'rwga_no_router', __( 'Generation router is not available.', 'reactwoo-geo-ai' ) );
		}

		$envelope = RWGA_Generation_Router::generate(
			$this->get_key(),
			array(
				'payload'        => $in,
				'local_callback' => array( $this, 'local_generation_callback' ),
			)
		);
		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}

		$norm = $this->normalise_response(
			isset( $envelope['engine_response'] ) && is_array( $envelope['engine_response'] )
				? $envelope['engine_response']
				: array()
		);
		return $this->finish_execute( $in, $norm );
	}

	/**
	 * Local deterministic callback for the generation router.
	 *
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Request envelope.
	 * @return array<string, mixed>
	 */
	public function local_generation_callback( $workflow_key, array $request ) {
		unset( $workflow_key );
		$payload = isset( $request['payload'] ) && is_array( $request['payload'] ) ? $request['payload'] : array();
		return $this->produce_stub( $payload );
	}

	/**
	 * @param array<string, mixed> $in   Sanitised payload.
	 * @param array<string, mixed> $norm Normalised result.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function finish_execute( array $in, array $norm ) {
		$persisted = $this->persist( $in, $norm );
		if ( is_array( $persisted ) && empty( $persisted['success'] ) ) {
			$msg = isset( $persisted['error'] ) ? (string) $persisted['error'] : __( 'Could not save competitor research.', 'reactwoo-geo-ai' );
			return new WP_Error( 'rwga_persist', $msg );
		}
		return $persisted;
	}

	/**
	 * @param array<string, mixed> $response Raw engine response.
	 * @return array<string, mixed>
	 */
	public function normalise_response( array $response ) {
		return array(
			'workflow_key'    => $this->get_key(),
			'agent_key'       => $this->get_agent_key(),
			'summary'         => isset( $response['summary'] ) ? (string) $response['summary'] : '',
			'strengths'       => isset( $response['strengths'] ) ? (string) $response['strengths'] : '',
			'weaknesses'      => isset( $response['weaknesses'] ) ? (string) $response['weaknesses'] : '',
			'patterns'        => isset( $response['patterns'] ) ? (string) $response['patterns'] : '',
			'opportunities'   => isset( $response['opportunities'] ) ? (string) $response['opportunities'] : '',
			'schema_version'  => self::DEFAULT_SCHEMA_VERSION,
		);
	}

	/**
	 * @param array<string, mixed> $input  Request payload.
	 * @param array<string, mixed> $result Normalised result.
	 * @return array<string, mixed>
	 */
	public function persist( array $input, array $result ) {
		$uid = get_current_user_id();

		$id = RWGA_DB_Competitor_Research::insert(
			array(
				'page_id'        => isset( $input['page_id'] ) && (int) $input['page_id'] > 0 ? (int) $input['page_id'] : null,
				'competitor_url' => isset( $input['competitor_url'] ) ? (string) $input['competitor_url'] : '',
				'page_type'      => isset( $input['page_type'] ) ? (string) $input['page_type'] : 'page',
				'geo_target'     => isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '',
				'workflow_key'   => $this->get_key(),
				'summary'        => isset( $result['summary'] ) ? (string) $result['summary'] : '',
				'strengths'      => isset( $result['strengths'] ) ? (string) $result['strengths'] : '',
				'weaknesses'     => isset( $result['weaknesses'] ) ? (string) $result['weaknesses'] : '',
				'patterns'       => isset( $result['patterns'] ) ? (string) $result['patterns'] : '',
				'opportunities'  => isset( $result['opportunities'] ) ? (string) $result['opportunities'] : '',
				'status'         => 'complete',
				'created_by'     => $uid > 0 ? $uid : null,
			)
		);

		if ( $id <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'Could not insert competitor research row.', 'reactwoo-geo-ai' ),
			);
		}

		$page_id = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		$geo     = isset( $input['geo_target'] ) ? (string) $input['geo_target'] : '';

		RWGA_Memory_Service::append(
			'competitor_research_saved',
			'competitor_research',
			$id,
			$page_id,
			$geo,
			array(
				'workflow_key'    => $this->get_key(),
				'competitor_url'  => isset( $input['competitor_url'] ) ? (string) $input['competitor_url'] : '',
			)
		);

		return array(
			'success'                 => true,
			'competitor_research_id'  => $id,
			'result'                  => $result,
		);
	}

	/**
	 * @param array<string, mixed> $in Sanitised payload.
	 * @return array<string, mixed>
	 */
	private function produce_stub( array $in ) {
		$url = isset( $in['competitor_url'] ) ? (string) $in['competitor_url'] : '';
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$host = is_string( $host ) ? $host : $url;

		$our = '';
		$pid = isset( $in['page_id'] ) ? (int) $in['page_id'] : 0;
		if ( $pid > 0 ) {
			$t = get_the_title( $pid );
			$our = is_string( $t ) ? $t : '';
		}

		$summary = sprintf(
			/* translators: 1: competitor host, 2: optional our page title */
			__( 'Bounded snapshot comparing positioning for “%1$s”%2$s. Replace stub text after you review the live page.', 'reactwoo-geo-ai' ),
			$host,
			$our ? ' ' . sprintf(
				/* translators: %s: page title */
				__( 'against your page “%s”.', 'reactwoo-geo-ai' ),
				$our
			) : '.'
		);

		$strengths = __( "Clear hero promise; scannable sections; social proof above the fold; strong primary CTA.\n(Stub — refine after visiting the URL.)", 'reactwoo-geo-ai' );
		$weaknesses = __( "Generic messaging in the mid-page; footer navigation competes with primary CTA; limited differentiation vs. alternatives.\n(Stub)", 'reactwoo-geo-ai' );
		$patterns = __( 'Repeating pattern: outcome-led headline → three benefit bullets → logo row → pricing/module cards.', 'reactwoo-geo-ai' );
		$opportunities = __( 'Test a sharper contrast block before pricing; add quantified proof; reduce parallel CTAs on mobile.', 'reactwoo-geo-ai' );

		/**
		 * Filter stub competitor research fields before normalisation.
		 *
		 * @param array<string, string> $fields Summary keys.
		 * @param array<string, mixed>    $in     Sanitised input.
		 */
		$fields = apply_filters(
			'rwga_competitor_research_stub_fields',
			array(
				'summary'       => $summary,
				'strengths'     => $strengths,
				'weaknesses'    => $weaknesses,
				'patterns'      => $patterns,
				'opportunities' => $opportunities,
			),
			$in
		);

		return is_array( $fields ) ? $fields : array(
			'summary'       => $summary,
			'strengths'     => $strengths,
			'weaknesses'    => $weaknesses,
			'patterns'      => $patterns,
			'opportunities' => $opportunities,
		);
	}
}
