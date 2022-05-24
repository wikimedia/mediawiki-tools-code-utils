#!/usr/bin/env php
<?php
// phpcs:disable Generic.Arrays.DisallowLongArraySyntax.Found
// phpcs:disable Generic.ControlStructures.DisallowYodaConditions.Found
// phpcs:disable MediaWiki.Commenting.FunctionComment.NotParenthesisParamName
// phpcs:disable MediaWiki.Commenting.FunctionComment.ParamNameNoMatch
// phpcs:disable MediaWiki.ControlStructures.AssignmentInControlStructures.AssignmentInControlStructures
// phpcs:disable MediaWiki.ExtraCharacters.ParenthesesAroundKeyword.ParenthesesAroundKeywords

/**
 * Recursive directory crawling PHP syntax checker
 * Uses parsekit, which is much faster than php -l for lots of files due to the
 * PHP startup overhead.
 *
 * @author Tim Starling
 * @author Timo Tijhof
 * @file
 */

require_once( __DIR__ . '/includes/MwCodeUtilsArgs.php' );

if ( PHP_SAPI != 'cli' ) {
	echo "This script must be run from the command line\n";
	exit( 1 );
}

$self = array_shift( $argv );

$verbose = false;
$paths = array();

if ( count( $argv ) ) {
	$args = new MwCodeUtilsArgs( $argv );
	$unknownArgs = array_diff( array_keys( $args->flags ), array( 'h', 'help', 'v', 'verbose' ) );
	if ( count( $unknownArgs ) ) {
		echo "error: unknown option '{$unknownArgs[0]}'\n\n";
		$args->flags['help'] = true;
	}

	if ( $args->flag( 'help' ) || $args->flag( 'h' ) ) {
		echo "usage: php $self [options] [<files/directories>..]

    -v, --verbose         Be verbose in output
    -h, --help            Show this message
";
		exit( 0 );
	}

	$verbose = $args->flag( 'verbose' ) || $args->flag( 'v' );
	$paths = $args->args;
}

if ( !count( $paths ) ) {
	$paths = array( '.' );
}

/**
 * @param string $dir
 * @param bool [$verbose=false]
 * @return bool
 */
function mwCodeUtils_lintDir( $dir, $verbose = false ) {
	if ( $verbose ) {
		print "... checking $dir\n";
	}
	$handle = opendir( $dir );
	if ( !$handle ) {
		return true;
	}
	$success = true;
	while ( false !== ( $fileName = readdir( $handle ) ) ) {
		if ( substr( $fileName, 0, 1 ) == '.' ) {
			continue;
		}
		if ( is_dir( "$dir/$fileName" ) ) {
			$ret = mwCodeUtils_lintDir( "$dir/$fileName", $verbose );
		} elseif ( substr( $fileName, -4 ) == '.php' ) {
			$ret = mwCodeUtils_lintFile( "$dir/$fileName", $verbose );
		} else {
			$ret = true;
		}
		$success = $success && $ret;
	}
	closedir( $handle );
	return $success;
}

/**
 * @param string $file
 * @param bool [$verbose=false]
 * @return bool
 */
function mwCodeUtils_lintFile( $file, $verbose = false ) {
	if ( $verbose ) {
		print "... checking $file\n";
	}
	static $okErrors = array(
		'Redefining already defined constructor',
		'Assigning the return value of new by reference is deprecated',
		# Allow this file to lint itself (https://bugs.php.net/64596)
		'Cannot redeclare mwCodeUtils_lintDir() (previously declared in',
		'Cannot redeclare mwCodeUtils_lintFile() (previously declared in',
	);
	$errors = array();
	parsekit_compile_file( $file, $errors, PARSEKIT_SIMPLE );
	$ret = true;
	if ( $errors ) {
		foreach ( $errors as $error ) {
			foreach ( $okErrors as $okError ) {
				if ( substr( $error['errstr'], 0, strlen( $okError ) ) == $okError ) {
					continue 2;
				}
			}
			$ret = false;
			print "Error in $file line {$error['lineno']}: {$error['errstr']}\n";
		}
	}
	return $ret;
}

$allOK = true;
foreach ( $paths as $path ) {
	if ( !file_exists( $path ) ) {
		echo "Path not found: $path\n";
		$allOK = false;
		continue;
	}
	$ret = is_dir( $path ) ? mwCodeUtils_lintDir( $path, $verbose ) : mwCodeUtils_lintFile( $path, $verbose );
	$allOK = $allOK && $ret;
}
if ( $allOK ) {
	if ( $verbose ) {
		echo "\nAll OK!\n";
	}
	exit( 0 );
} else {
	if ( $verbose ) {
		echo "\nOne or more errors.\n";
	}
	exit( 1 );
}
