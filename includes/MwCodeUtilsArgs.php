<?php
/**
 * From:
 * - http://code.google.com/p/tylerhall/source/browse/trunk/class.args.php
 * - http://clickontyler.com/blog/2008/11/parse-command-line-arguments-in-php/ (MIT license)
 *
 * Copyright (c) 2008 Tyler Hall
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE
 */
class MwCodeUtilsArgs {
	public $flags;
	public $args;

	public function __construct( $argv ) {
		$this->flags = array();
		$this->args  = array();

		for ( $i = 0; $i < count( $argv ); $i++ ) {
			$str = $argv[$i];

			// --foo
			if ( strlen( $str ) > 2 && substr( $str, 0, 2 ) == '--' ) {
				$str = substr( $str, 2 );
				$parts = explode( '=', $str );
				$this->flags[$parts[0]] = true;

				// Does not have an =, so choose the next arg as its value
				if ( count( $parts ) == 1 && isset( $argv[$i + 1] ) && preg_match( '/^--?.+/', $argv[$i + 1] ) == 0 ) {
					$this->flags[$parts[0]] = $argv[$i + 1];
				} elseif ( count( $parts ) == 2 ) {
					// Has a =, so pick the second piece
					$this->flags[$parts[0]] = $parts[1];
				}
			// -a
			} elseif ( strlen( $str ) == 2 && $str[0] == '-' ) {
				$this->flags[$str[1]] = true;
				if ( isset( $argv[$i + 1] ) && preg_match( '/^--?.+/', $argv[$i + 1] ) == 0 )
					$this->flags[$str[1]] = $argv[$i + 1];
			// -abcdef
			} elseif ( strlen( $str ) > 1 && $str[0] == '-' ) {
				for ( $j = 1; $j < strlen( $str ); $j++ )
					$this->flags[$str[$j]] = true;
			}
		}

		// Any arguments after the last - or --
		for ( $i = count( $argv ) - 1; $i >= 0; $i-- ) {
			if ( preg_match( '/^--?.+/', $argv[$i] ) == 0 )
				$this->args[] = $argv[$i];
			else
				break;
		}

		$this->args = array_reverse( $this->args );
	}

	public function flag( $name ) {
		return isset( $this->flags[$name] ) ? $this->flags[$name] : false;
	}
}
