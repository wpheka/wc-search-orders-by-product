<?php
// option.php NAME -- the stored option as JSON.
echo wp_json_encode( get_option( $args[0] ?? '' ) );
