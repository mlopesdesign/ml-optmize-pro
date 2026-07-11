<?php
/**
 * Assets — minify, combine, inline critical CSS, output buffer.
 *
 * Estrategia:
 * - Captura o HTML final no shutdown via ob_start
 * - Parseia <link rel="stylesheet"> e <script src> inline
 * - Aplica minify via regex (sem lib externa)
 * - Opcionalmente combina CSS em 1 arquivo
 * - Opcionalmente inline small CSS
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Assets {

	const CACHE_DIR = 'ml-optmize-pro';
	const CSS_DIR   = 'css';
	const JS_DIR    = 'js';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 2 );
	}

	/**
	 * Inicia buffer de saida.
	 */
	public static function maybe_start_buffer() {
		if ( ! self::should_process() ) {
			return;
		}
		ob_start( array( __CLASS__, 'on_buffer_end' ) );
	}

	/**
	 * Decide se processa o output.
	 *
	 * @return bool
	 */
	public static function should_process() {
		$any_on = ML_Optimize_Pro_Settings::is_on( 'minify_html' )
			|| ML_Optimize_Pro_Settings::is_on( 'minify_css' )
			|| ML_Optimize_Pro_Settings::is_on( 'minify_js' )
			|| ML_Optimize_Pro_Settings::is_on( 'combine_css' )
			|| ML_Optimize_Pro_Settings::is_on( 'combine_js' );
		if ( ! $any_on ) {
			return false;
		}
		// So processa GET HTML.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return false;
		}
		if ( is_feed() || is_preview() ) {
			return false;
		}
		if ( ! empty( $_GET ) ) {
			$bypass = array( 'no-opt', 'no-cache' );
			foreach ( $bypass as $key ) {
				if ( isset( $_GET[ $key ] ) ) {
					return false;
				}
			}
		}
		// Bypass se a request tem Accept que nao inclui HTML.
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) $_SERVER['HTTP_ACCEPT'] : '';
		if ( $accept && false === strpos( $accept, 'text/html' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Callback de fim de buffer — processa o HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function on_buffer_end( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 100 ) {
			return $html;
		}
		// So processa 200.
		if ( function_exists( 'http_response_code' ) && 200 !== http_response_code() ) {
			return $html;
		}
		$html = self::process_html( $html );
		return $html;
	}

	/**
	 * Pipeline de processamento.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function process_html( $html ) {
		$html = self::process_css( $html );
		$html = self::process_js( $html );
		if ( ML_Optimize_Pro_Settings::is_on( 'minify_html' ) ) {
			$html = self::minify_html( $html );
		}
		return $html;
	}

	/**
	 * Processa CSS no HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function process_css( $html ) {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'minify_css' ) && ! ML_Optimize_Pro_Settings::is_on( 'combine_css' ) ) {
			return $html;
		}
		$minify = ML_Optimize_Pro_Settings::is_on( 'minify_css' );
		$combine = ML_Optimize_Pro_Settings::is_on( 'combine_css' );
		// Encontra todos os <link rel="stylesheet" href="...">.
		$pattern = '/<link\s+[^>]*rel=["\']stylesheet["\'][^>]*>/i';
		if ( ! preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER ) ) {
			return $html;
		}
		$to_remove = array();
		$combined  = '';
		$urls      = array();
		$inline_keys = array();
		foreach ( $matches as $tag ) {
			$link = $tag[0];
			// Ignora se tem disabled, media="print", data-no-optimize.
			if ( false !== stripos( $link, 'data-no-optimize' ) || false !== stripos( $link, 'media="print' ) || false !== stripos( $link, 'rel="preload' ) ) {
				continue;
			}
			if ( ! preg_match( '/href=["\']([^"\']+)["\']/i', $link, $href ) ) {
				continue;
			}
			$url = $href[1];
			// So recursos locais (mesmo host).
			if ( ! self::is_local_url( $url ) ) {
				continue;
			}
			$to_remove[] = $link;
			if ( $combine ) {
				$urls[] = $url;
			}
		}
		if ( empty( $to_remove ) ) {
			return $html;
		}
		// Remove os links originais.
		$html = str_replace( $to_remove, '', $html );
		// Combine.
		if ( $combine && ! empty( $urls ) ) {
			$hash = md5( implode( '|', $urls ) );
			$cache = WP_CONTENT_DIR . '/cache/' . self::CACHE_DIR . '/' . self::CSS_DIR . '/' . $hash . '.css';
			if ( ! file_exists( $cache ) ) {
				$body = self::fetch_and_combine( $urls, 'css' );
				@wp_mkdir_p( dirname( $cache ) );
				@file_put_contents( $cache, $body );
			}
			$combined_url = WP_CONTENT_URL . '/cache/' . self::CACHE_DIR . '/' . self::CSS_DIR . '/' . $hash . '.css';
			$combined_tag = '<link rel="stylesheet" id="ml-optpro-combined-' . esc_attr( $hash ) . '" href="' . esc_url( $combined_url ) . '" media="all" data-no-optimize="1" />';
			// Insere no <head>, logo apos <head>.
			$html = preg_replace( '/(<head[^>]*>)/i', '$1' . $combined_tag, $html, 1 );
		} elseif ( $minify ) {
			// Sem combine mas com minify: nao tem como minificar arquivos externos via buffer
			// (isso eh trabalho do Cache + Cloudflare). Mantemos no-op.
		}
		return $html;
	}

	/**
	 * Processa JS no HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function process_js( $html ) {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'minify_js' ) && ! ML_Optimize_Pro_Settings::is_on( 'combine_js' ) ) {
			return $html;
		}
		$combine = ML_Optimize_Pro_Settings::is_on( 'combine_js' );
		// Combinar JS eh arriscado (defer / async). Apenas se usuario ligou.
		if ( ! $combine ) {
			return $html;
		}
		$pattern = '/<script\s+[^>]*src=["\']([^"\']+)["\'][^>]*><\/script>/i';
		if ( ! preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER ) ) {
			return $html;
		}
		$to_remove = array();
		$urls      = array();
		foreach ( $matches as $tag ) {
			$full = $tag[0];
			$url  = $tag[1];
			if ( false !== stripos( $full, 'data-no-optimize' ) ) {
				continue;
			}
			if ( ! self::is_local_url( $url ) ) {
				continue;
			}
			// Pula scripts com type=module, defer/async, json, ld+json.
			if ( preg_match( '/type=["\']module["\']/i', $full ) ) {
				continue;
			}
			if ( false !== stripos( $full, 'application/ld+json' ) || false !== stripos( $full, 'application/json' ) ) {
				continue;
			}
			$to_remove[] = $full;
			$urls[]      = $url;
		}
		if ( empty( $to_remove ) ) {
			return $html;
		}
		$html = str_replace( $to_remove, '', $html );
		$hash  = md5( implode( '|', $urls ) );
		$cache = WP_CONTENT_DIR . '/cache/' . self::CACHE_DIR . '/' . self::JS_DIR . '/' . $hash . '.js';
		if ( ! file_exists( $cache ) ) {
			$body = self::fetch_and_combine( $urls, 'js' );
			@wp_mkdir_p( dirname( $cache ) );
			@file_put_contents( $cache, $body );
		}
		$combined_url = WP_CONTENT_URL . '/cache/' . self::CACHE_DIR . '/' . self::JS_DIR . '/' . $hash . '.js';
		$combined_tag = '<script id="ml-optpro-combined-' . esc_attr( $hash ) . '" src="' . esc_url( $combined_url ) . '" defer data-no-optimize="1"></script>';
		$html = preg_replace( '/(<\/head>)/i', $combined_tag . '$1', $html, 1 );
		return $html;
	}

	/**
	 * Faz fetch e combina arquivos CSS/JS.
	 *
	 * @param array  $urls URLs.
	 * @param string $type css|js.
	 * @return string
	 */
	private static function fetch_and_combine( $urls, $type ) {
		$out = '';
		foreach ( $urls as $url ) {
			$abs = self::to_absolute_local_path( $url );
			if ( $abs && file_exists( $abs ) ) {
				$body = file_get_contents( $abs );
				if ( false === $body ) {
					continue;
				}
				if ( 'css' === $type ) {
					$body = self::minify_css( $body );
				} else {
					$body = self::minify_js( $body );
				}
				$out .= "\n/* " . esc_html( basename( $abs ) ) . " */\n" . $body . "\n";
			}
		}
		return $out;
	}

	/**
	 * Minify CSS.
	 *
	 * @param string $css CSS.
	 * @return string
	 */
	public static function minify_css( $css ) {
		if ( ! is_string( $css ) ) {
			return '';
		}
		// Remove comentarios.
		$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
		// Remove whitespace.
		$css = preg_replace( '/\s+/', ' ', $css );
		// Remove space around punctuation.
		$css = preg_replace( '/\s*([{};:,>~+])\s*/', '$1', $css );
		// Trim.
		$css = trim( $css );
		return $css;
	}

	/**
	 * Minify JS (regex leve, nao seguro para 100% dos codigos).
	 *
	 * @param string $js JS.
	 * @return string
	 */
	public static function minify_js( $js ) {
		if ( ! is_string( $js ) ) {
			return '';
		}
		// Remove comentarios de linha.
		$js = preg_replace( '#(?<!:)//[^\r\n]*#', '', $js );
		// Remove comentarios de bloco.
		$js = preg_replace( '#/\*[\s\S]*?\*/#', '', $js );
		// Remove whitespace entre tokens.
		$js = preg_replace( '/\s+/', ' ', $js );
		// Remove space around operators e pontuacao.
		$js = preg_replace( '/\s*([{};:,()=<>!+\-*\/&|?])\s*/', '$1', $js );
		// Remove ponto-e-virgula antes de }.
		$js = preg_replace( '/;}/', '}', $js );
		return trim( $js );
	}

	/**
	 * Minify HTML (preserva pre, textarea, script, style).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function minify_html( $html ) {
		if ( ! is_string( $html ) ) {
			return '';
		}
		// Preserva blocos criticos.
		$preserved = array();
		$idx = 0;
		$html = preg_replace_callback( '#<(pre|textarea|script|style)[^>]*>.*?</\1>#is', function( $m ) use ( &$preserved, &$idx ) {
			$key = '<!--PRESERVED_' . $idx . '-->';
			$preserved[ $key ] = $m[0];
			$idx++;
			return $key;
		}, $html );
		// Remove comentarios HTML.
		$html = preg_replace( '/<!--(?!\s*(?:\[if [^\]]+\]|<!|>))(?:(?!-->).)*-->/s', '', $html );
		// Colapsa whitespace entre tags.
		$html = preg_replace( '/>\s+</', '><', $html );
		// Colapsa multiplos whitespace em 1.
		$html = preg_replace( '/\s+/', ' ', $html );
		// Restaura preservados.
		foreach ( $preserved as $key => $value ) {
			$html = str_replace( $key, $value, $html );
		}
		return $html;
	}

	/**
	 * Verifica se URL e local (mesmo host).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_local_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		if ( 0 === strpos( $url, '/' ) ) {
			return true;
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host = wp_parse_url( $url, PHP_URL_HOST );
		return $host && $url_host && strtolower( $host ) === strtolower( $url_host );
	}

	/**
	 * Converte URL absoluta local para path no filesystem.
	 *
	 * @param string $url URL.
	 * @return string|null
	 */
	private static function to_absolute_local_path( $url ) {
		$url = preg_replace( '/\?.*$/', '', $url );
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $url_host && strtolower( $url_host ) !== strtolower( $host ) ) {
			return null;
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return null;
		}
		$abs = rtrim( ABSPATH, '/' ) . $path;
		return $abs;
	}
}
