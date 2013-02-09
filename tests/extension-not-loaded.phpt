<?php

function foo() {
	bcadd( '3', '5' ); // Warn
	if ( extension_loaded( 'bcmath' ) ) {
		bcadd( '3', '5' ); // Ok
	}
	bcadd( '3', '5' ); // Warn
	if ( true ) {
		bcadd( '3', '5' ); // Warn
	}
	return;
}
?>
--EXPECT--
Problems in extension-not-loaded.phpt:
 Function bcadd called in line 4 belongs to extension bcmath, but there was no check that bcmath was available.
 Function bcadd called in line 8 belongs to extension bcmath, but there was no check that bcmath was available.
 Function bcadd called in line 10 belongs to extension bcmath, but there was no check that bcmath was available.

