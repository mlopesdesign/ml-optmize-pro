<?php
/**
 * CDN — reescreve URLs estaticas para CDN configurado.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_CDN {

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		if ( ! ML_Optimize_Pro_Settings::is_on( 'cdn_enabled' ) ) {
			return;
		}
		add_filter( 'script_loader_src', array( __CLASS__, 'rewrite' ), 20 );
		add_filter( 'style_loader_src', array( __CLASS__, 'rewrite' ), 20 );
		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'rewrite' ), 20 );
		add_filter( 'the_content', array( __CLASS__, 'rewrite_content' ), 20 );
	}

	/**
	 * URL do CDN.
	 *
	 * @return string
	 */
	public static function cdn_url() {
		$url = (string) ML_Optimize_Pro_Settings::get( 'cdn_url', '' );
		return rtrim( $url, '/' );
	}

	/**
	 * Reescricao simples.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function rewrite( $url ) {
		$cdn = self::cdn_url();
		if ( ! $cdn || ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		return self::do_rewrite( $url, $cdn );
	}

	/**
	 * Reescreve URLs no conteudo.
	 *
	 * @param string $content Conteudo.
	 * @return string
	 */
	public static function rewrite_content( $content ) {
		$cdn = self::cdn_url();
		if ( ! $cdn || ! is_string( $content ) || '' === $content ) {
			return $content;
		}
		$excluded = (array) ML_Optimize_Pro_Settings::get( 'cdn_excludes', array() );
		$home     = home_url( '/' );
		$content = preg_replace_callback( '#(https?:)?//' . preg_quote( wp_parse_url( $home, PHP_URL_HOST ), '#' ) . '[^"\'\s<>)]+#i', function( $m ) use ( $cdn, $excluded, $home ) {
			$url = $m[0];
			if ( 0 === strpos( $url, '//' ) ) {
				$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
			}
			// Excluido.
			foreach ( $excluded as $ex ) {
				if ( '' !== $ex && false !== strpos( $url, $ex ) ) {
					return $m[0];
				}
			}
			return self::do_rewrite( $url, $cdn );
		}, $content );
		return $content;
	}

	/**
	 * Faz a reescrita.
	 *
	 * @param string $url URL.
	 * @param string $cdn CDN.
	 * @return string
	 */
	private static function do_rewrite( $url, $cdn ) {
		$home = home_url();
		// So reescreve URLs do mesmo host.
		$url_host = wp_parse_url( $url, PHP_URL_HOST );
		$home_host = wp_parse_url( $home, PHP_URL_HOST );
		if ( $url_host && strtolower( $url_host ) !== strtolower( $home_host ) ) {
			return $url;
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! $path ) {
			return $url;
		}
		$result = $cdn . $path;
		if ( $query ) {
			$result .= '?' . $query;
		}
		return $result;
	}
}
