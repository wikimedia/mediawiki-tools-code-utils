<?php

function findOrphanBlobs() {
	if ( !extension_loaded( 'gmp' ) ) {
		echo "Can't find orphan blobs, need bitfield support provided by GMP.\n";
		return;
	}
	$actualBlobs = gmp_init( 0 ); // Do not warn
}
?>
--EXPECT--

