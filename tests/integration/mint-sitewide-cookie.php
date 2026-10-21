<?php
/**
 * Print one rwgo_site cookie JSON whose arm matches the requested bucket.
 *
 * Env: RWGO_WANT=control|variant, RWGO_KEY, RWGO_WEIGHT.
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide.php';

$want   = isset( $_SERVER['RWGO_WANT'] ) ? (string) $_SERVER['RWGO_WANT'] : 'control';
$key    = isset( $_SERVER['RWGO_KEY'] ) ? (string) $_SERVER['RWGO_KEY'] : 'rwgo_sitewide';
$weight = isset( $_SERVER['RWGO_WEIGHT'] ) ? (float) $_SERVER['RWGO_WEIGHT'] : 0.5;

for ( $i = 0; $i < 5000; $i++ ) {
	$id = substr( hash( 'sha256', 'rwgo-staging-' . $i ), 0, 32 );
	if ( RWGO_Sitewide::bucket_arm( $id, $key, $weight ) === $want ) {
		echo rawurlencode(
			(string) json_encode(
				array(
					'id'  => $id,
					'arm' => $want,
					'exp' => $key,
				)
			)
		);
		exit( 0 );
	}
}

fwrite( STDERR, "no id for {$want}\n" );
exit( 1 );
