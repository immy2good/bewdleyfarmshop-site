<?php
/**
 * Deprecated status-check script.
 *
 * This file was intentionally disabled because the legacy implementation
 * contained hardcoded credentials and deprecated MySQL APIs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Content-Type: text/plain' );
}

http_response_code( 410 );
echo "Deprecated endpoint. This script is disabled for security reasons.\n";
exit;
