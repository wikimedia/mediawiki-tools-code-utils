#!/usr/bin/env php
<?php
$gerrit = "gerrit.wikimedia.org";
$baseUrl = "ssh://$gerrit:29418"; // "https://$gerrit/r/p" if you have no ssh access

$skipRepositories = array( 'test*', 'operations*', 'analytics*', 'labs*', 'integration*', 'wikimedia*', 'mediawiki/packages*', 'All-Projects', 'apps*', 'search*', 'translatewiki*', 'webplatform.org*', 'qa*', 'glam*' );

// curl included with Linux or msysgit (Win)
$command = 'curl -H "Accept: application/json; Content-Type: application/json; charset=utf-8" -X GET ' . "https://$gerrit/r/projects/?d";

$totalStart = microtime( true );
$p = popen( $command, "r" );
$json = stream_get_contents( $p );
pclose( $p );

$json = str_replace( ")]}'", '', $json ); // Fix leading text that breaks json_decode

$projects = json_decode( $json );

foreach ( $projects as $project ) {
	$repo = urldecode( $project->id );
	print "$repo\n";

	if ( strpos( strtolower( $project->description ), "deleted" ) !== false ) {
		echo " Don't use, skipped\n";
		continue;
	}

	foreach ( $skipRepositories as $skip ) {
		if ( fnmatch( $skip, $repo, FNM_CASEFOLD ) ) {
			echo " Skipped\n";
			continue 2;
		}
	}

	if ( file_exists( "$repo/.git" ) ) {
		echo " There's already a repository at $repo\n";
		continue;
	}

	$start = microtime( true );
	passthru( "git clone " . escapeshellarg( "$baseUrl/$repo.git" ) . " " . escapeshellarg( $repo ), $exitCode );
	$end = microtime( true );
	echo " $repo cloned in ", $end - $start, " seconds.\n";
	if ( $exitCode ) {
		echo " $repo clone failed.\n";
		exit( $exitCode );
	}
}
$totalEnd = microtime( true );

echo "Total time: ", $totalEnd - $totalStart, " seconds\n";
