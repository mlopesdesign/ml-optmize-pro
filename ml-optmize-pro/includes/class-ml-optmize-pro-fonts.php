<?php
/**
 * Fonts — self-host Google Fonts, combine, preload, font-display swap.
 *
 * Estrategia:
 * 1) Detecta CSS Google Fonts no output.
 * 2) Faz download local para /wp-content/uploads/ml-optmize-pro/fonts/.
 * 3) Reescreve URLs para local.
 * 4) Adiciona preload das WOFF2.
 * 5) Adiciona font-display: swap.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Fonts {

	const FONTS_DIR_OPTION = 'ml_optimize_pro_fonts_dir';
	const FONTS_DIR_NAME   = 'ml-optmize-pro-fonts';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		$any_on = ML_Optimize_Pro_Settings::is_on( 'fonts_self_host' )
			|| ML_Optimize_Pro_Settings::is_on( 'fonts_combine' )
			|| ML_Optimize_Pro_Settings::is_on( 'fonts_preload' )
			|| ML_Optimize_Pro_Settings::is_on( 'fonts_display_swap' );
		if ( ! $any_on ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 6 );
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
		// 1) Adiciona font-display: swap no CSS.
		if ( ML_Optimize_Pro_Settings::is_on( 'fonts_display_swap' ) ) {
			$html = self::add_font_display_swap( $html );
		}
		// 2) Self-host Google Fonts.
		if ( ML_Optimize_Pro_Settings::is_on( 'fonts_self_host' ) ) {
			$html = self::self_host_google_fonts( $html );
		}
		// 3) Preload manual de URLs extras.
		if ( ML_Optimize_Pro_Settings::is_on( 'fonts_preload' ) ) {
			$preload_urls = self::get_preload_urls();
			if ( ! empty( $preload_urls ) ) {
				$tags = '';
				foreach ( $preload_urls as $url ) {
					$tags .= '<link rel="preload" href="' . esc_url( $url ) . '" as="font" type="font/woff2" crossorigin />';
				}
				$html = preg_replace( '/(<head[^>]*>)/i', '$1' . $tags, $html, 1 );
			}
		}
		return $html;
	}

	/**
	 * Adiciona font-display: swap em @font-face.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function add_font_display_swap( $html ) {
		// Em <style> inline.
		$html = preg_replace_callback( '#@font-face\s*\{([^}]+)\}#i', function( $m ) {
			$body = $m[1];
			if ( false !== stripos( $body, 'font-display' ) ) {
				return $m[0];
			}
			$body = rtrim( $body, '; ' ) . '; font-display:swap';
			return '@font-face{' . $body . '}';
		}, $html );
		return $html;
	}

	/**
	 * Self-host Google Fonts — detecta <link rel=stylesheet href="fonts.googleapis.com...">
	 * e faz download do CSS e dos WOFF2 locais, reescrevendo URLs.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function self_host_google_fonts( $html ) {
		$pattern = '#<link\s+[^>]*href=["\'](https?:)?//fonts\.googleapis\.com/css[^"\']*["\'][^>]*>#i';
		if ( ! preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER ) ) {
			return $html;
		}
		$dir = self::get_fonts_dir();
		foreach ( $matches as $link ) {
			$original = $link[0];
			$url      = self::extract_url( $original );
			if ( ! $url ) {
				continue;
			}
			$css_content = self::fetch_url( $url );
			if ( ! $css_content ) {
				continue;
			}
			// Baixa arquivos de fonte referenciados.
			$css_content = preg_replace_callback( '#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#i', function( $m ) use ( $url ) {
				$font_url = $m[1];
				// Resolve relativo.
				if ( 0 !== strpos( $font_url, 'http' ) && 0 !== strpos( $font_url, '//' ) ) {
					$base = substr( $url, 0, strrpos( $url, '/' ) + 1 );
					$font_url = $base . $font_url;
				}
				if ( 0 === strpos( $font_url, '//' ) ) {
					$font_url = 'https:' . $font_url;
				}
				$local = self::download_font( $font_url, $dir );
				if ( $local ) {
					return 'url(' . $local . ')';
				}
				return $m[0];
			}, $css_content );
			// Adiciona font-display: swap.
			if ( false === stripos( $css_content, 'font-display' ) ) {
				$css_content = preg_replace_callback( '#@font-face\s*\{([^}]+)\}#i', function( $m ) {
					$body = rtrim( $m[1], '; ' ) . ';font-display:swap';
					return '@font-face{' . $body . '}';
				}, $css_content );
			}
			$inline = '<style id="ml-optmize-pro-fonts-selfhost">' . $css_content . '</style>';
			$html   = str_replace( $original, $inline, $html );
		}
		return $html;
	}

	/**
	 * Extrai href de uma tag.
	 *
	 * @param string $tag Tag.
	 * @return string|null
	 */
	private static function extract_url( $tag ) {
		if ( preg_match( '/href=["\']([^"\']+)["\']/i', $tag, $m ) ) {
			return $m[1];
		}
		return null;
	}

	/**
	 * Faz fetch de uma URL.
	 *
	 * @param string $url URL.
	 * @return string|null
	 */
	private static function fetch_url( $url ) {
		$response = wp_remote_get( $url, array(
			'timeout'   => 10,
			'sslverify' => false,
			'headers'   => array(
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
			),
		) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Baixa fonte para local.
	 *
	 * @param string $url URL.
	 * @param string $dir Diretorio.
	 * @return string|null URL local ou null.
	 */
	private static function download_font( $url, $dir ) {
		$body = self::fetch_url( $url );
		if ( ! $body ) {
			return null;
		}
		$filename = 'font_' . md5( $url ) . ( false !== strpos( $url, '.woff2' ) ? '.woff2' : ( false !== strpos( $url, '.woff' ) ? '.woff' : '.ttf' ) );
		$path     = $dir . $filename;
		@file_put_contents( $path, $body );
		$upload_dir = wp_upload_dir();
		return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $path );
	}

	/**
	 * Diretorio de fontes.
	 *
	 * @return string
	 */
	private static function get_fonts_dir() {
		$upload = wp_upload_dir();
		$dir    = $upload['basedir'] . '/' . self::FONTS_DIR_NAME . '/';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * URLs de preload manual.
	 *
	 * @return array
	 */
	private static function get_preload_urls() {
		$raw = (string) ML_Optimize_Pro_Settings::get( 'fonts_preload_urls', '' );
		return array_filter( array_map( 'trim', preg_split( "/[\r\n]+/", $raw ) ) );
	}
}
