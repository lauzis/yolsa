<?php

namespace Lauzis\WpPackages\I18n;

/**
 * Which language a site, a request, or a post is in.
 *
 * WordPress has no answer to this. A multilingual site has whichever plugin it
 * has, each with its own API, and a site with none still has a language. Every
 * plugin that needs to know ends up writing the same cascade, so it lives here
 * once.
 *
 * WPML and Polylang are handled directly because between them they cover most
 * sites. Anything else — TranslatePress, WeGlot, a bespoke setup — answers
 * through the filters rather than by being added here: the list of translation
 * plugins is not something this package can keep up with, and a site always
 * knows better than a guess.
 *
 * Codes are returned the way the active plugin reports them, which is a bare
 * language code such as "lv". With no plugin at all the locale's language part
 * is used, so "lv_LV" answers as "lv" and codes stay comparable either way.
 * Ask locale() when the region matters.
 */
class Language {

	/** Returned when nothing can be determined at all. */
	const UNKNOWN = '';

	/**
	 * Language of the current request.
	 *
	 * @return string A language code, or '' if nothing could be determined.
	 */
	public static function current() {
		$code = self::from_wpml_current();

		if ( self::UNKNOWN === $code ) {
			$code = self::from_polylang_current();
		}

		if ( self::UNKNOWN === $code ) {
			$code = self::normalize( self::locale() );
		}

		/**
		 * Filters the detected language of the current request.
		 *
		 * The extension point for any translation plugin not handled directly.
		 *
		 * @param string $code Detected code, possibly ''.
		 */
		return (string) apply_filters( 'wp_packages_current_language', $code );
	}

	/**
	 * Language a particular post is in.
	 *
	 * Asked of the post rather than the request wherever a plugin can answer:
	 * the two agree on an ordinary page view, but a post looked up in the
	 * background — a cron job, a REST call, a sync — has a language while the
	 * request has nothing meaningful to say.
	 *
	 * @param int $post_id
	 * @return string A language code, or '' if nothing could be determined.
	 */
	public static function for_post( $post_id ) {
		$post_id = (int) $post_id;
		$code    = self::UNKNOWN;

		if ( $post_id > 0 ) {
			$code = self::from_wpml_post( $post_id );

			if ( self::UNKNOWN === $code ) {
				$code = self::from_polylang_post( $post_id );
			}
		}

		if ( self::UNKNOWN === $code ) {
			$code = self::current();
		}

		/**
		 * Filters the detected language of a post.
		 *
		 * @param string $code    Detected code, possibly ''.
		 * @param int    $post_id
		 */
		return (string) apply_filters( 'wp_packages_post_language', $code, $post_id );
	}

	/** The site's default language, as opposed to the one being viewed. */
	public static function default_language() {
		$code = self::UNKNOWN;

		if ( self::has_filter( 'wpml_default_language' ) ) {
			$code = self::clean( apply_filters( 'wpml_default_language', null ) );
		}

		if ( self::UNKNOWN === $code && function_exists( 'pll_default_language' ) ) {
			$code = self::clean( pll_default_language() );
		}

		if ( self::UNKNOWN === $code ) {
			$code = self::normalize( self::site_locale() );
		}

		return (string) apply_filters( 'wp_packages_default_language', $code );
	}

	/**
	 * Every language the site publishes in.
	 *
	 * A single-language site answers with its own one code rather than an empty
	 * list, so a caller can loop without special-casing.
	 *
	 * @return string[]
	 */
	public static function available() {
		$codes = array();

		if ( self::has_filter( 'wpml_active_languages' ) ) {
			$languages = apply_filters( 'wpml_active_languages', null );

			if ( is_array( $languages ) ) {
				foreach ( $languages as $key => $language ) {
					$code = is_array( $language ) && isset( $language['language_code'] )
						? $language['language_code']
						: $key;

					$code = self::clean( $code );

					if ( self::UNKNOWN !== $code ) {
						$codes[] = $code;
					}
				}
			}
		}

		if ( ! $codes && function_exists( 'pll_languages_list' ) ) {
			$languages = pll_languages_list();

			if ( is_array( $languages ) ) {
				foreach ( $languages as $code ) {
					$code = self::clean( $code );

					if ( self::UNKNOWN !== $code ) {
						$codes[] = $code;
					}
				}
			}
		}

		if ( ! $codes ) {
			$current = self::current();
			$codes   = self::UNKNOWN === $current ? array() : array( $current );
		}

		return array_values( array_unique( apply_filters( 'wp_packages_available_languages', $codes ) ) );
	}

	/** Whether more than one language is in play. */
	public static function is_multilingual() {
		return count( self::available() ) > 1;
	}

	/**
	 * Which system answered, for logging and for a settings screen to report.
	 *
	 * @return string 'wpml', 'polylang', 'filter' or 'none'.
	 */
	public static function source() {
		if ( self::has_filter( 'wpml_current_language' ) ) {
			return 'wpml';
		}

		if ( function_exists( 'pll_current_language' ) ) {
			return 'polylang';
		}

		if ( self::has_filter( 'wp_packages_current_language' ) ) {
			return 'filter';
		}

		return 'none';
	}

	/**
	 * The full locale a language code corresponds to, e.g. "lv" => "lv_LV".
	 *
	 * A translation plugin reports a bare code but also knows the locale behind
	 * it, and the two answer different questions: the code groups content, the
	 * locale is what WordPress serves and what names a translation file. Asking
	 * for one and reading the other off the current request is how a post in
	 * Latvian ends up labelled en_US on an admin screen.
	 *
	 * @param string $code
	 * @return string The locale, or the code itself if nothing knows better.
	 */
	public static function locale_for( $code ) {
		$code = self::clean( $code );

		if ( self::UNKNOWN === $code ) {
			return self::locale();
		}

		$active = apply_filters( 'wpml_active_languages', null );

		if ( is_array( $active ) ) {
			foreach ( $active as $key => $language ) {
				$reported = is_array( $language ) && isset( $language['language_code'] )
					? $language['language_code']
					: $key;

				if ( self::clean( $reported ) === $code && ! empty( $language['default_locale'] ) ) {
					return (string) $language['default_locale'];
				}
			}
		}

		if ( function_exists( 'pll_languages_list' ) ) {
			$slugs   = (array) pll_languages_list( array( 'fields' => 'slug' ) );
			$locales = (array) pll_languages_list( array( 'fields' => 'locale' ) );
			$index   = array_search( $code, array_map( array( __CLASS__, 'clean' ), $slugs ), true );

			if ( false !== $index && isset( $locales[ $index ] ) ) {
				return (string) $locales[ $index ];
			}
		}

		// No plugin to ask. The request's locale is the answer when it is the
		// same language, and otherwise the code is the best that can be said.
		return self::normalize( self::locale() ) === $code ? self::locale() : $code;
	}

	/** The full locale of the current request, e.g. "lv_LV". */
	public static function locale() {
		if ( function_exists( 'determine_locale' ) ) {
			return (string) determine_locale();
		}

		return function_exists( 'get_locale' ) ? (string) get_locale() : '';
	}

	/**
	 * A locale reduced to its language part: "lv_LV" becomes "lv".
	 *
	 * @param string $locale
	 * @return string
	 */
	public static function normalize( $locale ) {
		$locale = self::clean( $locale );

		if ( self::UNKNOWN === $locale ) {
			return self::UNKNOWN;
		}

		$parts = preg_split( '/[_-]/', $locale );

		return strtolower( $parts[0] );
	}

	/** @return string */
	private static function from_wpml_current() {
		if ( ! self::has_filter( 'wpml_current_language' ) ) {
			return self::UNKNOWN;
		}

		return self::clean( apply_filters( 'wpml_current_language', null ) );
	}

	/** @return string */
	private static function from_polylang_current() {
		if ( ! function_exists( 'pll_current_language' ) ) {
			return self::UNKNOWN;
		}

		return self::clean( pll_current_language() );
	}

	/**
	 * @param int $post_id
	 * @return string
	 */
	private static function from_wpml_post( $post_id ) {
		if ( ! self::has_filter( 'wpml_post_language_details' ) ) {
			return self::UNKNOWN;
		}

		$details = apply_filters( 'wpml_post_language_details', null, $post_id );

		// WPML answers with a WP_Error for a post type it does not translate.
		if ( ! is_array( $details ) || empty( $details['language_code'] ) ) {
			return self::UNKNOWN;
		}

		return self::clean( $details['language_code'] );
	}

	/**
	 * @param int $post_id
	 * @return string
	 */
	private static function from_polylang_post( $post_id ) {
		if ( ! function_exists( 'pll_get_post_language' ) ) {
			return self::UNKNOWN;
		}

		return self::clean( pll_get_post_language( $post_id ) );
	}

	/** The locale configured for the site, ignoring the current request. */
	private static function site_locale() {
		if ( function_exists( 'get_locale' ) ) {
			return (string) get_locale();
		}

		return '';
	}

	/**
	 * Whether anything is listening, so an absent plugin is not mistaken for a
	 * plugin that answered with nothing.
	 *
	 * @param string $hook
	 * @return bool
	 */
	private static function has_filter( $hook ) {
		return function_exists( 'has_filter' ) && has_filter( $hook );
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private static function clean( $value ) {
		if ( ! is_string( $value ) ) {
			return self::UNKNOWN;
		}

		return trim( $value );
	}
}
