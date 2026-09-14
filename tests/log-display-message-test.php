<?php
/** Minimal behavior test for Persian admin-facing log messages. */

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/class-logger.php';

$known_message = TVES_Logger::display_message( 'Torob Product API v3 request completed.' );
$unknown_message = TVES_Logger::display_message( 'Custom exception message.' );

if ( 'درخواست API نسخه ۳ ترب با موفقیت پاسخ داده شد.' !== $known_message ) {
	throw new RuntimeException( 'A known technical log message was not presented in Persian.' );
}

if ( 'Custom exception message.' !== $unknown_message ) {
	throw new RuntimeException( 'An unknown diagnostic message was changed.' );
}

echo "log-display-message-test: ok\n";
