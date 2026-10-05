<?php
// usermeta.php get|clear <meta key> -- the test administrator's meta.
require __DIR__ . '/lib.php';
$ids = sobp_t_ids();
if ( 'clear' === $args[0] ) {
	delete_user_meta( (int) $ids['admin'], $args[1] );
	echo '"cleared"';
} else {
	echo wp_json_encode( get_user_meta( (int) $ids['admin'], $args[1], true ) );
}
