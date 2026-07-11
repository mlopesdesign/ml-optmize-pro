<?php
/**
 * Remove Unused CSS — async / loadCSS pattern para eliminar render-blocking.
 *
 * Estrategia (padrao FlyingPress / Perfmatters): reescreve <link rel=stylesheet>
 * para preload + onload=this.rel='stylesheet', com fallback <noscript>. Mantem
 * a pagina renderizando sem bloqueio.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Remove_Unused_CSS {

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		if ( ! ML_Optimize_Pro_Settings::is_on( 'remove_unused_css' ) && ! ML_Optimize_Pro_Settings::is_on( 'load_css_async' ) ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 4 );
	}

	/**
	 * Inicia buffer.
	 */
	public static function maybe_start_buffer() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( is_feed() || is_preview() ) {
			return;
		}
		ob_start( array( __CLASS__, 'on_buffer_end' ) );
	}

	/**
	 * Processa buffer.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function on_buffer_end( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 100 ) {
			return $html;
		}
		if ( function_exists( 'http_response_code' ) && 200 !== http_response_code() ) {
			return $html;
		}
		$async = ML_Optimize_Pro_Settings::is_on( 'load_css_async' ) || ML_Optimize_Pro_Settings::is_on( 'remove_unused_css' );
		if ( ! $async ) {
			return $html;
		}
		$method = ML_Optimize_Pro_Settings::get( 'unused_css_method', 'async' );
		$safe   = self::get_safelist();
		// Encontra todos os <link rel="stylesheet" href="...">.
		$pattern = '/<link\s+[^>]*rel=["\']stylesheet["\'][^>]*>/i';
		if ( ! preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER ) ) {
			return $html;
		}
		foreach ( $matches as $tag ) {
			$link = $tag[0];
			// Se ja tem onload ou data-no-optimize, ignora.
			if ( false !== stripos( $link, 'data-no-optimize' ) || false !== stripos( $link, ' onload' ) ) {
				continue;
			}
			// Ignora se for print ou preload.
			if ( false !== stripos( $link, 'media="print' ) || false !== stripos( $link, 'rel="preload' ) ) {
				continue;
			}
			// Pega href.
			if ( ! preg_match( '/href=["\']([^"\']+)["\']/i', $link, $href ) ) {
				continue;
			}
			$url = $href[1];
			// Aplica safelist.
			if ( self::url_matches( $url, $safe ) ) {
				continue;
			}
			$replacement = self::build_async_tag( $url, $method );
			$html = str_replace( $link, $replacement, $html );
		}
		return $html;
	}

	/**
	 * Constroi tag async.
	 *
	 * @param string $url    URL.
	 * @param string $method async|remove.
	 * @return string
	 */
	private static function build_async_tag( $url, $method ) {
		if ( 'remove' === $method ) {
			return '<noscript><link rel="stylesheet" href="' . esc_url( $url ) . '" /></noscript>';
		}
		return '<link rel="preload" href="' . esc_url( $url ) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\';" /><noscript><link rel="stylesheet" href="' . esc_url( $url ) . '" /></noscript>';
	}

	/**
	 * Pega safelist (URLs que nao devem ser atrasadas).
	 *
	 * @return array
	 */
	private static function get_safelist() {
		$raw = (string) ML_Optimize_Pro_Settings::get( 'unused_css_safelist', '' );
		$list = array_filter( array_map( 'trim', preg_split( "/[\r\n]+/", $raw ) ) );
		// Safelist padrao.
		$default = array(
			'wp-admin',
			'wp-includes',
			'fonts.googleapis.com',
			'fonts.gstatic.com',
			'wp-content/plugins/ml-optmize-pro',
		);
		return array_unique( array_merge( $default, $list ) );
	}

	/**
	 * Verifica se URL bate com algum pattern da safelist.
	 *
	 * @param string $url URL.
	 * @param array  $list Lista.
	 * @return bool
	 */
	private static function url_matches( $url, $list ) {
		foreach ( $list as $pattern ) {
			if ( '' === $pattern ) {
				continue;
			}
			if ( false !== strpos( $url, $pattern ) ) {
				return true;
			}
		}
		return false;
	}
}
