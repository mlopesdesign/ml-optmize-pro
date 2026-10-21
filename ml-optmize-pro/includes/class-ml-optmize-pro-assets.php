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
		// Sem combinar não há arquivo substituto: os links ficam intactos. (Antes, "Minify CSS"
		// sozinho removia todos os CSS locais da página — o site perdia o layout.)
		if ( ! $combine ) {
			return $html;
		}
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
			// Nunca mexe no CSS do tema (regra ML: plugin não altera o template).
			if ( self::is_theme_asset( $url ) ) {
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
		$hash  = md5( implode( '|', $urls ) );
		$cache = WP_CONTENT_DIR . '/cache/' . self::CACHE_DIR . '/' . self::CSS_DIR . '/' . $hash . '.css';
		if ( ! self::ensure_combined_file( $cache, $urls, 'css' ) ) {
			// Sem arquivo combinado válido, nada é removido: a página continua com seus CSS.
			return $html;
		}
		$combined_url = WP_CONTENT_URL . '/cache/' . self::CACHE_DIR . '/' . self::CSS_DIR . '/' . $hash . '.css';
		$combined_tag = '<link rel="stylesheet" id="ml-optpro-combined-' . esc_attr( $hash ) . '" href="' . esc_url( $combined_url ) . '" media="all" data-no-optimize="1" />';
		// O combinado entra no lugar do primeiro link removido (mantém a ordem da cascata)
		// e os demais saem. Se a troca falhar, devolve o HTML original intacto.
		$first = array_shift( $to_remove );
		$pos   = strpos( $html, $first );
		if ( false === $pos ) {
			return $html;
		}
		$out = substr_replace( $html, $combined_tag, $pos, strlen( $first ) );
		$out = str_replace( $to_remove, '', $out );
		return false !== strpos( $out, $combined_tag ) ? $out : $html;
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
			// Nunca mexe no JS do tema (regra ML: plugin não altera o template).
			if ( self::is_theme_asset( $url ) ) {
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
		$hash  = md5( implode( '|', $urls ) );
		$cache = WP_CONTENT_DIR . '/cache/' . self::CACHE_DIR . '/' . self::JS_DIR . '/' . $hash . '.js';
		if ( ! self::ensure_combined_file( $cache, $urls, 'js' ) ) {
			// Sem arquivo combinado válido, nada é removido.
			return $html;
		}
		$combined_url = WP_CONTENT_URL . '/cache/' . self::CACHE_DIR . '/' . self::JS_DIR . '/' . $hash . '.js';
		$combined_tag = '<script id="ml-optpro-combined-' . esc_attr( $hash ) . '" src="' . esc_url( $combined_url ) . '" defer data-no-optimize="1"></script>';
		$out = preg_replace( '/(<\/head>)/i', $combined_tag . '$1', $html, 1 );
		if ( ! is_string( $out ) || false === strpos( $out, $combined_tag ) ) {
			return $html;
		}
		return str_replace( $to_remove, '', $out );
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
			// Marcador que NÃO parece comentário HTML: antes era <!--PRESERVED_n--> e a remoção
			// de comentários logo abaixo apagava todos os <script>/<style>/<pre>/<textarea>.
			$key = "\x1AMLOPT_PRESERVED_" . $idx . "\x1A";
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
	 * URL pertence ao tema ativo (pai ou filho)? Esses arquivos nunca são alterados.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_theme_asset( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		foreach ( array( get_stylesheet_directory_uri(), get_template_directory_uri() ) as $theme_url ) {
			$theme_path = (string) wp_parse_url( $theme_url, PHP_URL_PATH );
			if ( '' !== $theme_path && 0 === strpos( $path, trailingslashit( $theme_path ) ) ) {
				return true;
			}
		}
		return false !== strpos( $path, '/wp-content/themes/' );
	}

	/**
	 * Garante o arquivo combinado no disco, com conteúdo. Falso = não combinar (nada é removido).
	 *
	 * @param string $file Caminho do arquivo.
	 * @param array  $urls URLs de origem.
	 * @param string $type css|js.
	 * @return bool
	 */
	private static function ensure_combined_file( $file, $urls, $type ) {
		if ( file_exists( $file ) && filesize( $file ) > 0 ) {
			return true;
		}
		$body = self::fetch_and_combine( $urls, $type );
		if ( ! is_string( $body ) || '' === trim( $body ) ) {
			return false;
		}
		@wp_mkdir_p( dirname( $file ) );
		return false !== @file_put_contents( $file, $body ) && filesize( $file ) > 0;
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
