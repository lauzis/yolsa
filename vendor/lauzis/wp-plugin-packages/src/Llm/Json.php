<?php

namespace Lauzis\WpPackages\Llm;

/**
 * Helpers for the part of an LLM response that is never quite reliable: getting
 * a JSON payload out of text a model produced.
 *
 * Kept separate from Client so a plugin can reuse it on a response obtained some
 * other way.
 */
class Json {

	/**
	 * Extracts the first JSON array from a block of model output.
	 *
	 * Models routinely wrap the array in prose, a ```json fence, or an object
	 * with a single key. All three are unwrapped here rather than in every
	 * consuming plugin.
	 *
	 * @param string $text
	 * @return array|null Decoded array, or null when none could be found.
	 */
	public static function extract_array( $text ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return null;
		}

		// Strip a fenced code block if the whole response is wrapped in one.
		if ( preg_match( '/^```(?:json)?\s*(.+?)\s*```$/is', $text, $m ) ) {
			$text = trim( $m[1] );
		}

		$decoded = json_decode( $text, true );

		if ( is_array( $decoded ) ) {
			// A bare array is the expected shape; an object wrapping one is a
			// common variation, so unwrap a single array-valued key.
			if ( self::is_list( $decoded ) ) {
				return $decoded;
			}

			foreach ( $decoded as $value ) {
				if ( is_array( $value ) && self::is_list( $value ) ) {
					return $value;
				}
			}
		}

		// Fall back to the first bracketed span in the text.
		$start = strpos( $text, '[' );
		$end   = strrpos( $text, ']' );

		if ( false === $start || false === $end || $end < $start ) {
			return null;
		}

		$decoded = json_decode( substr( $text, $start, $end - $start + 1 ), true );

		return ( is_array( $decoded ) && self::is_list( $decoded ) ) ? $decoded : null;
	}

	/**
	 * Describes an unusable response for a log or an error message, without
	 * dumping the whole thing.
	 *
	 * @param string $text
	 * @param int    $limit
	 * @return string
	 */
	public static function describe( $text, $limit = 300 ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return 'empty response';
		}

		$snippet = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
		$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );

		return $length > $limit ? $snippet . '… (' . $length . ' chars)' : $snippet;
	}

	/**
	 * array_is_list(), which is PHP 8.1+; this package supports 7.4.
	 *
	 * @param array $array
	 * @return bool
	 */
	private static function is_list( array $array ) {
		// An empty array is a list. range( 0, -1 ) counts downwards and yields
		// [0, -1], so the naive comparison would reject it.
		if ( array() === $array ) {
			return true;
		}

		return array_keys( $array ) === range( 0, count( $array ) - 1 );
	}
}
