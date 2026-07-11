<?php
/**
 * Normalises remote intelligence workflow responses to a stable JSON shape.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured intelligence payload: summary, findings, recommendations, actions, usage.
 */
class RWGA_Intelligence_Response {

	const SCHEMA_VERSION = '1.0.0';

	/**
	 * @param array<string, mixed> $response Raw engine response.
	 * @param string               $workflow_key Workflow key.
	 * @return array<string, mixed>
	 */
	public static function normalise( array $response, $workflow_key = '' ) {
		$workflow_key = sanitize_key( (string) $workflow_key );

		$out = array(
			'workflow_key'   => $workflow_key,
			'schema_version' => self::SCHEMA_VERSION,
			'summary'        => isset( $response['summary'] ) ? sanitize_text_field( (string) $response['summary'] ) : '',
			'findings'       => self::normalise_findings( $response ),
			'recommendations' => self::normalise_recommendations( $response ),
			'actions'        => self::normalise_actions( $response ),
			'usage'          => self::normalise_usage( $response ),
		);

		/**
		 * Filter normalised intelligence workflow response.
		 *
		 * @param array<string, mixed> $out          Normalised response.
		 * @param array<string, mixed> $response     Raw engine response.
		 * @param string               $workflow_key Workflow key.
		 */
		return apply_filters( 'rwga_intelligence_response', $out, $response, $workflow_key );
	}

	/**
	 * @param array<string, mixed> $response Raw response.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalise_findings( array $response ) {
		$rows = isset( $response['findings'] ) && is_array( $response['findings'] ) ? $response['findings'] : array();
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'id'          => isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '',
				'severity'    => self::sanitize_severity( $row['severity'] ?? 'info' ),
				'title'       => isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '',
				'detail'      => isset( $row['detail'] ) ? sanitize_text_field( (string) $row['detail'] ) : ( isset( $row['evidence'] ) ? sanitize_text_field( (string) $row['evidence'] ) : '' ),
				'entity_type' => isset( $row['entity_type'] ) ? sanitize_key( (string) $row['entity_type'] ) : '',
				'entity_id'   => isset( $row['entity_id'] ) ? sanitize_text_field( (string) $row['entity_id'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $response Raw response.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalise_recommendations( array $response ) {
		$rows = isset( $response['recommendations'] ) && is_array( $response['recommendations'] ) ? $response['recommendations'] : array();
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'id'       => isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '',
				'priority' => self::sanitize_severity( $row['priority'] ?? 'medium' ),
				'title'    => isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '',
				'detail'   => isset( $row['detail'] ) ? sanitize_text_field( (string) $row['detail'] ) : ( isset( $row['recommendation'] ) ? sanitize_text_field( (string) $row['recommendation'] ) : '' ),
			);
		}
		return $out;
	}

	/**
	 * Actions are approval-based only — never auto-apply.
	 *
	 * @param array<string, mixed> $response Raw response.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalise_actions( array $response ) {
		$rows = isset( $response['actions'] ) && is_array( $response['actions'] ) ? $response['actions'] : array();
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$action_json = isset( $row['action_json'] ) && is_array( $row['action_json'] ) ? $row['action_json'] : array();
			$out[]       = array(
				'action_type'        => isset( $row['action_type'] ) ? sanitize_key( (string) $row['action_type'] ) : '',
				'label'              => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
				'action_json'        => $action_json,
				'requires_approval'  => true,
				'status'             => 'pending',
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $response Raw response.
	 * @return array<string, mixed>
	 */
	private static function normalise_usage( array $response ) {
		$usage = isset( $response['usage'] ) && is_array( $response['usage'] ) ? $response['usage'] : array();
		return array(
			'charged_units' => isset( $usage['charged_units'] ) ? (int) $usage['charged_units'] : ( isset( $usage['units'] ) ? (int) $usage['units'] : 1 ),
			'cache_hit'     => ! empty( $usage['cache_hit'] ),
			'provider'      => isset( $usage['provider'] ) ? sanitize_key( (string) $usage['provider'] ) : 'reactwoo',
			'display_label' => isset( $usage['display_label'] ) ? sanitize_text_field( (string) $usage['display_label'] ) : __( 'AI usage', 'reactwoo-geo-ai' ),
		);
	}

	/**
	 * @param mixed $value Raw severity/priority.
	 * @return string
	 */
	private static function sanitize_severity( $value ) {
		$v = sanitize_key( (string) $value );
		$allowed = array( 'low', 'medium', 'high', 'info', 'critical' );
		return in_array( $v, $allowed, true ) ? $v : 'medium';
	}
}
