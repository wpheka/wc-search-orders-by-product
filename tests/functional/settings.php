<?php
// settings.php -- the stored sobp_settings as JSON.
echo wp_json_encode( get_option( 'sobp_settings' ) );
