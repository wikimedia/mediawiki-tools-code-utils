#!/usr/bin/env php
<?php
// phpcs:disable Generic.Arrays.DisallowLongArraySyntax.Found
// phpcs:disable Generic.Functions.FunctionCallArgumentSpacing.SpaceBeforeComma
// phpcs:disable MediaWiki.Commenting.DocComment.SyntaxAlignedDocClose
// phpcs:disable MediaWiki.WhiteSpace.MultipleEmptyLines.MultipleEmptyLines
// phpcs:disable MediaWiki.WhiteSpace.SpaceAfterControlStructure.Incorrect
// phpcs:disable MediaWiki.WhiteSpace.SpaceBeforeSingleLineComment.NewLineComment
// phpcs:disable PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
// phpcs:disable Squiz.PHP.NonExecutableCode.Unreachable
// phpcs:disable Squiz.Scope.MethodScope.Missing
// phpcs:disable Squiz.WhiteSpace.FunctionSpacing.After
// phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
// phpcs:disable Squiz.WhiteSpace.SemicolonSpacing.Incorrect
/**
 * Simple tool to count the number of line in PHP functions.
 *
 * Copyright © 2010-2011 Ashar Voultoiz <hashar@free.fr>
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
 * @file
*/

/**
 * Below are some debugging notes. Mainly the PHP token we use.
 *
 * T_WHITESPACE \t  \r\n
 * T_FUNCTION
 * T_ENDDECLARE
 * T_DECLARE
 * 0 : token index
 * 1 : content
 * 2 : line number
 *
 * @param $token_array An array of tokens as returned by PHP token_get_all()
 */
function _analyze_tokens( $token_array ) {
	$state = array(
		'function'    => '', # function name
		'fdepth'      => null, # function depth in the brace hierarchy
		'fstart_line' => 0, # function declaration start line

		'class'       => '', # class name
		'cdepth'      => null, # class depth in the brace hierarchy
		'cstart_line' => 0, # class declaration start line

		'cur_line' => 0, # current line analyzed
		'line'     => 0, # line number
		'depth'    => 0, # braces depth
	);

	$tokens = new TokenIterator( $token_array );
	$tokens->rewind();

	// loop while we get tokens from the iterator
	while ( $tokens->valid() ) {
		$token = $tokens->current();

		# handles braces returned as strings by token_get_all()
		if ( is_string( $token ) ) {
			if ( $token == '{' ) {
				$state['depth']++;
			} elseif ( $token == '}' ) {
				$state['depth']--;
				if ( $state['depth'] === $state['cdepth'] ) {
					$l = $state['cur_line'] - $state['cstart_line'];
					print "Done with class {$state['class']}, $l lines long.\n\n";
					$state['class']  = '';
					$state['cdepth'] = null;
					$state['cstart_line'] = null;
				} elseif ( $state['depth'] === $state['fdepth'] ) {
					$l = $state['cur_line'] - $state['fstart_line'];
					printf( "%5s lines for %s.\n", $l, $state['function'] );
					$state['function']  = '';
					$state['fdepth']    = null;
					$state['fstart_line'] = null;
				}
			} else {
				print "Got unwanted string: $token\n"; }

			debug_state( $state, $token );

			$tokens->next();
			continue;
		}

		$state['cur_line'] = $token[2];

		# handles CLASS and FUNCTION tokens
		switch ( $token[0] ) {
			case T_CURLY_OPEN:
				$state['depth']++;
				break;
			case T_CLASS:
			case T_FUNCTION:
				# find the token giving function or class name
				$name_token = $tokens->nextOfKind( T_STRING );

				if ( $token[0] == T_CLASS ) {
					$state['cdepth'] = $state['depth'];
					$state['cstart_line'] = $state['cur_line'];
					$state['class']  = $name_token[1];
					print "Analyzing class {$state['class']}\n";
				} else {
					$state['fdepth'] = $state['depth'];
					$state['fstart_line'] = $state['cur_line'];
					if ( $state['class'] ) {
						$state['function'] = $state['class'] . '::' . $name_token[1];
					} else {
						$state['function'] = $name_token[1];
					}
				}

			default:
				debug_state( $state, $token );
		}

		$tokens->next();
	}
}

/**
 * Debugging function used to output a token position in the brace hierarchy
 */
function debug_state( $state, $token = null ) {
	return;
	printf( "cdepth: %s, fdepth: %s, depth: %s | ",
		$state['cdepth'],
		$state['fdepth'],
		$state['depth']
	);
	if ( is_array( $token ) ) {
		printf( "line %s - %s: %s\n", $token[2], token_name( $token[0] ), $token[1] );
	} else {
		print "string $token\n";
	}
}

/**
 * A basic iterator extending the PHP ArrayIterator class.
 */
class TokenIterator extends ArrayIterator {
	function __construct( $array ) {
		parent::__construct( $array );
	}

	/**
	 * Skip tokens until we reach the wanted token, return it.
	 */
	function nextOfKind( $wanted_token_index ) {
		while ( true ) {
			$this->next();
			$token = $this->current();
			if ( $token[0] == $wanted_token_index ) {
				return $token;
			}
		}
	}
}

// We just keep blocks
$wanted_tokens   = array( T_FUNCTION, T_STRING, T_CLASS, T_IF, T_WHILE, T_SWITCH, T_CURLY_OPEN );
$wanted_strings  = array( '{', '}' );
$unwanted_tokens = array( T_WHITESPACE );

/**
 * Callback used to get ride of unneeded tokens.
 * @param mixed A PHP Token
 * @return bool Whether to keep the token
 */
function _filter( $token ) {
	global $wanted_tokens, $wanted_strings, $unwanted_tokens ;
	if ( false && is_array( $token ) ) {
		return in_array( $token[0], $wanted_tokens );
	}
	if ( is_array( $token ) ) {
		return !in_array( $token[0], $unwanted_tokens );
	}
	if ( is_string( $token ) ) {
		return in_array( $token, $wanted_strings );
	}
	return true;
}

function analyze_file( $file ) {
	$content = file_get_contents( $file );
	if ( $content === false ) {
		print "Could not open file $file\n";
		return null;
	}

	$tokens = token_get_all( $content );
	$tokens = array_filter( $tokens, '_filter' );
	_analyze_tokens( $tokens );
}


# Print usage when no source file given
if ( $argc == 1 ) {
	die( "Usage: $argv[0] <PHP_source_file> [<PHP_source_file> [...]]\n" );
}
array_shift( $argv );  // skip script name

# Parse each file given as an argument, one after the other...
foreach ( $argv as $arg ) {
	print "Trying file $arg...\n";
	analyze_file( $arg );
}
