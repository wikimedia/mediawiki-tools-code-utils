#!/usr/bin/env php
<?php
$gerrit = "gerrit.wikimedia.org";
$baseUrl = "ssh://$gerrit:29418"; // "https://$gerrit/r/p" if you have no ssh access

$skipRepositories = array( 'test*', 'operations*', 'analytics*', 'labs*', 'integration*', 'wikimedia*', 'mediawiki/tools*', );

// curl included with Linux or msysgit (Win)
$command = 'curl -H "Accept: application/json" -H "Content-Type: application/json; charset=utf-8"' .
	' -X POST -d "{\"jsonrpc\":\"2.0\",\"method\":\"visibleProjects\",\"params\":[],\"id\":1}"' .
	" https://$gerrit/r/gerrit/rpc/ProjectAdminService";

$totalStart = microtime( true );
$p = popen( $command, "r" );
$json = stream_get_contents( $p );
pclose( $p );

$rpc = json_decode( $json );
$projects = $rpc->result;

//print_r( $projects );

foreach ( $projects as $project ) {
	$repo = $project->name->name;
	print "$repo\n";

	if ( strpos( $project->description, "DELETE" ) !== false ) {
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
