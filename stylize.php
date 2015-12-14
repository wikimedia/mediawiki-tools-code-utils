#!/usr/bin/env php
<?php

/**
 * A PHP code beautifier aimed at adding lots of spaces to files that lack them,
 * in keeping with MediaWiki's spacey site style.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @author Tim Starling
 * @author Jeroen De Dauw
 * @file
 */

require_once( __DIR__ . '/includes/MwCodeUtilsArgs.php' );

if ( PHP_SAPI != 'cli' ) {
	echo "This script must be run from the command line\n";
	exit( 1 );
}

array_shift( $argv );

if ( count( $argv ) ) {
	$args = new MwCodeUtilsArgs( $argv );

	if ( $args->flag( 'help' ) ) {
		echo "Usage: php stylize.php [--backup|--help|--ignore=<regextoexclude>] <files/directories>
	backup : Creates a backup of modified files
	help : This message!
	ignore : Regex of files not to stylize e.g. .*\.i18n\.php
	<files/directories> : Files/directories to stylize
";

		return;
	}

	$ignore = $args->flag( 'ignore' );
	$backup = $args->flag( 'backup' );

	foreach ( $args->args as $dirOrFile ) {
		if ( is_dir( $dirOrFile ) ) {
			stylize_recursively( $dirOrFile, $ignore, $backup );
		} else {
			stylize_file( $dirOrFile, $backup );
		}
	}
} else {
	stylize_file( '-' );
}

function stylize_recursively( $dir, $ignore = false, $backup = false ) {
	$dir = trim( $dir, "\/" );

	foreach ( glob( "$dir/*" ) as $dirOrFile ) {
		if ( $ignore && preg_match( '/' . $ignore . '$/', $dirOrFile ) ) {
			echo "Ignoring $dirOrFile\n";
			continue;
		}

		if ( is_dir( $dirOrFile ) ) { // It's a directory, so call this function again.
			stylize_recursively( $dirOrFile, $ignore, $backup );
		} elseif ( is_file( $dirOrFile ) ) { // It's a file, so let's stylize it.
			// Only stylize php and js files, omitting minified js files.
			if ( preg_match( '/\.(php|php5|js)$/', $dirOrFile ) && !preg_match( '/\.(min\.js)$/', $dirOrFile ) ) {
				stylize_file( $dirOrFile, $backup );
			}
		}
	}
}

function stylize_file( $filename, $backup = true ) {
	echo "Stylizing file $filename\n";

	$s = ( $filename == '-' )
		? file_get_contents( '/dev/stdin' )
		: file_get_contents( $filename );

	if ( $s === false ) {
		return;
	}

	$stylizer = new Stylizer( $s );
	$s = $stylizer->stylize();

	if ( $filename == '-' ) {
		echo $s;
	} else {
		if ( $backup ) {
			rename( $filename, "$filename~" );
		}
		file_put_contents( $filename, $s );
	}
}

class Stylizer {
	var $tokens, $p;

	static $tablesInitialised = false;
	static $xSpaceBefore, $xSpaceAfter;

	static $space = array(
		T_WHITESPACE,
		'START',
		'END',
	);
	static $spaceBothSides = array(
		T_AND_EQUAL,
		T_AS,
		T_BOOLEAN_AND,
		T_BOOLEAN_OR,
		T_CASE,
		T_CATCH,
		T_CLONE,
		T_CONCAT_EQUAL,
		T_DIV_EQUAL,
		T_DO,
		T_DOUBLE_ARROW,
		T_ELSE,
		T_ELSEIF,
		T_FOR,
		T_FOREACH,
		T_IF,
		T_IS_EQUAL,
		T_IS_GREATER_OR_EQUAL,
		T_IS_IDENTICAL,
		T_IS_NOT_EQUAL,
		T_IS_NOT_IDENTICAL,
		T_IS_SMALLER_OR_EQUAL,
		T_LOGICAL_AND,
		T_LOGICAL_OR,
		T_LOGICAL_XOR,
		T_MOD_EQUAL,
		T_MUL_EQUAL,
		T_OR_EQUAL,
		T_PLUS_EQUAL,
		T_SL,
		T_SL_EQUAL,
		T_SR,
		T_SR_EQUAL,
		T_TRY,
		T_WHILE,
		T_XOR_EQUAL,
		'{',
		'%',
		'^',
		// '&', can be unary, we have a special case for =&
		'*',
		'=',
		'+',
		'|',
		'.',
		'<',
		'>',
		'/',
		'?',
	);
	static $spaceBefore = array(
		')',
		'}',
		'-', // $foo = -1; shouldn't change to $foo = - 1;
	);
	static $spaceAfter = array(
		'(',
		';',
		',',
		':', // can be a case label
	);
	static $closePairs = array(
		'(' => ')',
		'=' => '&',
		'{' => '}',
		'?' => ':',
		T_OBJECT_OPERATOR => '{',
	);

	// Tokens that eat spaces after them
	static $spaceEaters = array(
		T_COMMENT,
		T_OPEN_TAG,
		T_OPEN_TAG_WITH_ECHO,
	);

	var $endl = "
";

	function __construct( $s ) {
		$s = str_replace( "\r\n", "\n", $s );
		$this->tokens = token_get_all( $s );
		if ( !self::$tablesInitialised ) {
			self::$xSpaceBefore = array_combine(
				array_merge( self::$spaceBefore, self::$spaceBothSides ),
				array_fill( 0, count( self::$spaceBefore ) + count( self::$spaceBothSides ), true )
			);
			self::$xSpaceAfter = array_combine(
				array_merge( self::$spaceAfter, self::$spaceBothSides ),
				array_fill( 0, count( self::$spaceAfter ) + count( self::$spaceBothSides ), true )
			);
		}
	}

	function get( $i ) {
		if ( $i < 0 ) {
			return array( 'START', '' );
		} elseif ( $i >= count( $this->tokens ) ) {
			return array( 'END', '' );
		} else {
			$token = $this->tokens[$i];
			if ( is_string( $token ) ) {
				return array( $token, $token );
			} else {
				return array( $token[0], $token[1] );
			}
		}
	}

	function getCurrent() {
		return $this->get( $this->p );
	}

	function getPrev() {
		return $this->get( $this->p - 1 );
	}

	function getNext() {
		return $this->get( $this->p + 1 );
	}

	function isSpace( $token ) {
		if ( in_array( $token[0], self::$space ) ) {
			return true;
		}
		// Some other tokens can eat whitespace
		if ( in_array( $token[0], self::$spaceEaters ) && preg_match( '/\s$/', $token[1] ) ) {
			return true;
		}
		return false;
	}

	function isSpaceBefore( $token ) {
		return isset( self::$xSpaceBefore[$token[0]] );
	}

	function isSpaceAfter( $token ) {
		return isset( self::$xSpaceAfter[$token[0]] );
	}

	function consumeUpTo( $endType ) {
		$token = $this->getCurrent();
		$out = $token[1];
		do {
			$this->p++;
			$token = $this->getCurrent();
			$out .= $token[1];
		} while ( $this->p < count( $this->tokens ) && $token[0] != $endType );
		return $out;
	}

	function stylize() {
		$out = '';
		for ( $this->p = 0; $this->p < count( $this->tokens ); $this->p++ ) {
			list( $prevType, ) = $prevToken = $this->getPrev();
			list( $curType, $curText ) = $curToken = $this->getCurrent();
			list( $nextType, ) = $nextToken = $this->getNext();

			// Don't format strings
			if ( $curType == '"' ) {
				$out .= $this->consumeUpTo( '"' );
				continue;
			} elseif ( $curType == T_START_HEREDOC ) {
				$out .= $this->consumeUpTo( T_END_HEREDOC );
				continue;
			} elseif ( $curType == "'" ) {
				// For completeness
				$out .= $this->consumeUpTo( "'" );
				continue;
			}

			// Detect close pairs like ()
			$closePairBefore = isset( self::$closePairs[$prevType] )
				&& $curType == self::$closePairs[$prevType];
			$closePairAfter = isset( self::$closePairs[$curType] )
				&& $nextType == self::$closePairs[$curType];

			// Add space before
			if ( $this->isSpaceBefore( $curToken )
				&& !$this->isSpace( $prevToken )
				&& !$closePairBefore
			) {
					$out .= ' ';
			}

			// Add the token contents
			if ( $curType == T_COMMENT ) {
				$curText = $this->fixComment( $curText );
			}

			$out .= $curText;

			if ( substr( $out, -1 ) === "\n" ) {
				$out = $this->fixWhitespace( $out );
			}

			$wantSpaceAfter = $this->isSpaceAfter( $curToken );
			// Special case: space after =&
			if ( $prevType == '=' && $curType == '&' ) {
				$wantSpaceAfter = true;
			}

			// Add space after
			if ( $wantSpaceAfter
				&& !$closePairAfter
				&& !$this->isSpace( $nextToken )
				&& !$this->isSpaceBefore( $nextToken )
			) {
				$out .= ' ';
			}
		}
		$out = str_replace( "\n", $this->endl, $out );
		return $out;
	}

	function fixComment( $s ) {
		// Fix single-line comments with no leading whitespace
		if ( preg_match( '!^(#++|//++)(\S.*)$!s', $s, $m ) ) {
			$s = $m[1] . ' ' . $m[2];
		}
		return $s;
	}

	function fixWhitespace( $s ) {
		// Fix whitespace at the line end
		return preg_replace( "#[\t ]*\n#", "\n", $s );
	}
}
