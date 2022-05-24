#!/usr/bin/env php
<?php
// phpcs:disable MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation
// phpcs:disable Generic.Arrays.DisallowLongArraySyntax.Found
// phpcs:disable MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation
// phpcs:disable MediaWiki.Commenting.FunctionComment.WrongStyle
// phpcs:disable MediaWiki.WhiteSpace.SpaceyParenthesis.SingleSpaceAfterOpenParenthesis
// phpcs:disable MediaWiki.WhiteSpace.SpaceyParenthesis.SingleSpaceBeforeCloseParenthesis

/**
 * Scan a PHP codebase for incorrectly capitalized class names.
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
 * @author Kevin Israel
 * @todo Test and/or fix namespace support
 * @todo Add support for PHP 5.4 traits
 * @file
 */

// Debugging code, not currently in use
function tokenDump( $token ) {
	if ( is_array( $token ) ) {
		printf( "%6d: %-40s%s\n", $token[2], token_name( $token[0] ), $token[1] );
	} else {
		echo str_repeat( ' ', 38 ) . $token . "\n";
	}
}

class TokenScanner {
	protected $tokens;
	protected $token = null;
	protected $i = 0;

	public function __construct( $tokens ) {
		$this->tokens = $tokens;
	}

	protected function accept( $expected ) {
		if ( !is_array( $expected ) ) {
			$expected = array( $expected );
		}

		$this->nextToken();
		$got = is_array( $this->token ) ? $this->token[0] : $this->token;
		if ( in_array( $got, $expected, true ) ) {
			return true;
		}

		$this->i -= 2;
		$this->nextToken();
		return false;
	}

	protected function nextToken() {
		if ( $this->i >= count( $this->tokens ) ) {
			$this->token = null;
		} else {
			$this->token = $this->tokens[$this->i++];
		}
		return $this->token;
	}

	public function getDeclaredClasses() {
		$classes = array();
		$namespace = '\\';
		while ( $this->nextToken() ) {
			switch ( $this->token[0] ) {
				case T_NAMESPACE:
					$namespace = $this->parseNamespaceDeclaration();
					break;
				case T_CLASS:
				case T_INTERFACE:
					if ( !$this->accept( T_STRING ) ) {
						break;
					}
					$classes[] = $namespace . $this->token[1];
					break;
			}
		}
		return $classes;
	}

	public function getUsedClasses() {
		$classes = array();
		$namespace = '\\';
		$aliases = array();
		while ( $this->nextToken() ) {
			switch ( $this->token[0] ) {
				case T_NAMESPACE:
					$namespace = $this->parseNamespaceDeclaration();
					break;
				case T_USE:
					$full = '\\';
					$this->accept( T_NS_SEPARATOR );
					while ( $this->accept( array( T_STRING, T_NS_SEPARATOR ) ) ) {
						$full .= $this->token[1];
					}

					if ( $this->accept( ';' ) ) {
						$alias = substr( $full, strrpos( $full, '\\' ) + 1 );
					} elseif ( $this->accept( T_AS ) && $this->accept( T_STRING ) ) {
						$alias = $this->token[1];
					} else {
						break;
					}

					$aliases[strtolower($alias)] = $full;
					break;
				case T_NEW:
				case T_EXTENDS:
				case T_IMPLEMENTS:
					$className = $this->parseClassUse( $namespace, $aliases );
					if ( $className === null ) {
						break;
					}
					$classes[] = array( $className, $this->token[2] );
					break;
				case T_NS_SEPARATOR:
				case T_STRING:
					$this->i--;
					$className = $this->parseClassUse( $namespace, $aliases );
					if ( $className === null ) {
						$this->i++;
						break;
					}
					if ( !$this->accept( array( T_PAAMAYIM_NEKUDOTAYIM, T_VARIABLE ) ) ) {
						break;
					}
					$classes[] = array( $className, $this->token[2] );
					break;
			}
		}
		return $classes;
	}

	protected function parseNamespaceDeclaration() {
		if ( $this->accept( '{' ) ) {
			$namespace = '\\';
		} else {
			$namespace = '';
			do {
				if ( !$this->accept( T_STRING ) ) {
					return $namespace;
				}
				$namespace .= '\\' . $this->token[1];
			} while ( $this->accept( T_NS_SEPARATOR ) );
		}
		return $namespace;
	}

	protected function parseClassUse( $namespace, $aliases ) {
		if ( !$this->accept( array( T_NAMESPACE, T_NS_SEPARATOR, T_STRING ) ) ) {
			return null;
		}

		switch ( $this->token[0] ) {
			case T_NAMESPACE:
				$full = $namespace;
				break;
			case T_NS_SEPARATOR:
				if ( !$this->accept( T_STRING ) ) {
					return null;
				}
				$full = '\\' . $this->token[1];
				break;
			case T_STRING:
				$full = $this->token[1];
				if ( in_array( $full, array( 'parent', 'self', 'static' ) ) ) {
					return null;
				} elseif ( isset( $aliases[strtolower( $full )] ) ) {
					$full = $aliases[strtolower( $full )];
				} elseif ( $namespace !== '\\' ) {
					$full = $namespace . '\\' . $full;
				} else {
					$full = '\\' . $full;
				}
				break;
		}

		while ( $this->accept( T_NS_SEPARATOR ) ) {
			if ( !$this->accept( T_STRING ) ) {
				return null;
			}
			$full .= '\\' . $this->token[1];
		}

		return $full;
	}
}

if ( $argc < 3 ) {
	echo "Usage: {$argv[0]} declaration-path use-path\n";
	exit( 1 );
}

echo "Building class map...\n";

$map = array();
$iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $argv[1] ) );

foreach ( $iter as $fi ) {
	if ( !$fi->isFile() || !preg_match( '/\.(?:php|inc)$/', $fi->getFilename() ) ) {
		continue;
	}

	$rawTokens = token_get_all( file_get_contents( $fi->getPathname() ) );
	$tokens = array();
	foreach ( $rawTokens as $token ) {
		if ( $token[0] !== T_WHITESPACE ) {
			$tokens[] = $token;
		}
	}

	$ts = new TokenScanner( $tokens );
	foreach ( $ts->getDeclaredClasses() as $class ) {
		$map[strtolower( $class )] = $class;
	}
}

echo "Scanning for incorrectly capitalized class names...\n";

$iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $argv[2] ) );

foreach ( $iter as $fi ) {
	if ( !$fi->isFile() || !preg_match( '/\.(?:php|inc)$/', $fi->getFilename() ) ) {
		continue;
	}

	$pathname = $fi->getPathname();
	$rawTokens = token_get_all( file_get_contents( $pathname ) );
	$tokens = array();
	foreach ( $rawTokens as $token ) {
		if ( $token[0] !== T_WHITESPACE ) {
			$tokens[] = $token;
		}
	}

	$ts = new TokenScanner( $tokens );
	foreach ( $ts->getUsedClasses() as $arr ) {
		$line = $arr[1];
		$lowerClassName = strtolower( $arr[0] );
		if ( isset( $map[$lowerClassName] ) ) {
			$expectedClassName = $map[$lowerClassName];
			$actualClassName = $arr[0];
			if ( $actualClassName !== $expectedClassName ) {
				echo "$pathname:$line: expected $expectedClassName, found $actualClassName\n";
			}
		}
	}
}
