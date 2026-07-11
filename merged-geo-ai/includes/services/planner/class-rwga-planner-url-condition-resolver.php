<?php
/**
 * Extract URL / path / referrer conditions from clause text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Url_Condition_Resolver {

	/**
	 * @param string $clause Clause text.
	 * @return array<int,string>
	 */
	public static function extract_paths( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$paths  = array();

		if ( preg_match_all( '#/(?:[\w-]+/)*[\w-]+#', $clause, $matches ) ) {
			foreach ( (array) ( $matches[0] ?? array() ) as $path ) {
				$path = (string) $path;
				if ( '' !== $path && ! in_array( $path, $paths, true ) ) {
					$paths[] = $path;
				}
			}
		}

		if ( preg_match( '/\blanding\s+from\s+(\/[\w-]+)/i', $clause, $m ) ) {
			$path = (string) $m[1];
			if ( ! in_array( $path, $paths, true ) ) {
				$paths[] = $path;
			}
		}

		return $paths;
	}
}
