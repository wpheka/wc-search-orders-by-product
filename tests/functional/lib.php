<?php
/**
 * Shared helpers for the functional suite, loaded through WP-CLI eval-file.
 */
function sobp_t_state( $file ) {
	return rtrim( getenv( 'SOBP_STATE' ), '/' ) . '/' . $file;
}
function sobp_t_ids() {
	$path = sobp_t_state( 'ids.json' );
	return file_exists( $path ) ? json_decode( file_get_contents( $path ), true ) : array();
}
function sobp_t_save_ids( $ids ) {
	file_put_contents( sobp_t_state( 'ids.json' ), wp_json_encode( $ids ) );
}
function sobp_t_result( $name, $ok, $detail = '' ) {
	$line = ( $ok ? 'PASS' : 'FAIL' ) . '|' . $name . ( $ok || '' === $detail ? '' : ' -- ' . $detail );
	file_put_contents( sobp_t_state( 'results' ), $line . "\n", FILE_APPEND );
	echo $line, "\n";
}
