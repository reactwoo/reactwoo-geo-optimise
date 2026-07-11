<?php
/**
 * Temporary storage for confirmed assistant proposals.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Proposal_Store {

	const TTL = 900;

	/**
	 * @param array<string,mixed> $proposal Proposal payload.
	 * @return string Proposal ID.
	 */
	public static function save( array $proposal ) {
		$id = wp_generate_uuid4();
		set_transient(
			self::key( $id ),
			array(
				'proposal' => $proposal,
				'user_id'  => get_current_user_id(),
				'created'  => time(),
			),
			self::TTL
		);
		return $id;
	}

	/**
	 * @param string $id Proposal ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		$row = get_transient( self::key( $id ) );
		if ( ! is_array( $row ) || empty( $row['proposal'] ) ) {
			return null;
		}
		if ( (int) ( $row['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return null;
		}
		return is_array( $row['proposal'] ) ? $row['proposal'] : null;
	}

	/**
	 * @param string $id Proposal ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		return delete_transient( self::key( $id ) );
	}

	/**
	 * @param string $id Proposal ID.
	 * @return string
	 */
	private static function key( $id ) {
		return 'rwga_proposal_' . sanitize_key( $id );
	}
}
