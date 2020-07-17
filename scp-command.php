<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$wpcli_scp_autoloader = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $wpcli_scp_autoloader ) ) {
	require_once $wpcli_scp_autoloader;
}

WP_CLI::add_command( 'scp', 'Scp_Command' );
