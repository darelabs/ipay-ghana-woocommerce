<?php

/**
 * Exit if not invoked by WordPress.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

function ipay_ghana_uninstall_options() {
	delete_option( 'success-url' );
    delete_option( 'merchant-key' );
    delete_option( 'cancelled-url' );
    delete_option( 'source' );
    delete_option( 'extra_project_name' );
}
ipay_ghana_uninstall_options();