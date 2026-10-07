<?php
/**
 * browser-user.php create|delete -- a temporary administrator for browser-timing.js.
 * create prints JSON with cookies and the admin URL.
 */
$login = 'zz_sobp_perf';
if ( 'delete' === ( $args[0] ?? '' ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	$user = get_user_by( 'login', $login );
	if ( $user ) {
		foreach ( get_posts( array( 'author' => $user->ID, 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		wp_delete_user( $user->ID );
	}
	echo 'deleted';
	return;
}
$uid = username_exists( $login ) ? username_exists( $login ) : wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'zz-sobp-perf@example.invalid', 'role' => 'administrator' ) );
$exp = time() + HOUR_IN_SECONDS;
echo wp_json_encode(
	array(
		'admin_url' => admin_url(),
		'cookies'   => array(
			array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'auth' ), 'domain' => 'localhost', 'path' => wp_parse_url( admin_url(), PHP_URL_PATH ) ),
			array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'logged_in' ), 'domain' => 'localhost', 'path' => wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ),
		),
	)
);
