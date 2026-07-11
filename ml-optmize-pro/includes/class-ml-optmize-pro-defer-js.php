<?php
/**
 * Defer JS — adiciona defer em scripts nao criticos via filtro de script_loader_tag.
 *
 * Estrategia padrao Perfmatters: defer por padrao, exclusoes via configuracao.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Defer_JS {

	const CACHE_KEY_EXCLUDED = 'ml_optimize_pro_defer_excluded';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		if ( ! ML_Optimize_Pro_Settings::is_on( 'defer_js' ) ) {
			return;
		}
		add_filter( 'script_loader_tag', array( __CLASS__, 'add_defer' ), 20, 2 );
		add_filter( 'clean_url', array( __CLASS__, 'add_defer_to_external' ), 20, 3 );
	}

	/**
	 * Adiciona defer em scripts WP enfileirados.
	 *
	 * @param string $tag    Tag <script>.
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function add_defer( $tag, $handle ) {
		if ( self::is_excluded( $handle ) ) {
			return $tag;
		}
		// Nao defer em scripts com async, type=module, ou ja defer.
		if ( false !== stripos( $tag, ' async' ) || false !== stripos( $tag, ' defer' ) || false !== stripos( $tag, 'type="module"' ) ) {
			return $tag;
		}
		// Nao defer scripts no admin (mas a gente ja bloqueou is_admin).
		return str_replace( ' src=', ' defer src=', $tag );
	}

	/**
	 * Adiciona defer em scripts inline externos (clean_url filter).
	 *
	 * @param string $url          URL.
	 * @param string $original_url Original.
	 * @param string $context      Contexto.
	 * @return string
	 */
	public static function add_defer_to_external( $url, $original_url, $context ) {
		return $url;
	}

	/**
	 * Verifica se handle esta nas exclusoes.
	 *
	 * @param string $handle Handle.
	 * @return bool
	 */
	public static function is_excluded( $handle ) {
		$excluded = self::default_excluded();
		$excluded = apply_filters( 'ml_optimize_pro_defer_excluded', $excluded );
		return in_array( $handle, (array) $excluded, true );
	}

	/**
	 * Lista default de exclusoes (scripts criticos que nao podem ser defered).
	 *
	 * @return array
	 */
	public static function default_excluded() {
		return array(
			'jquery-core',
			'jquery-migrate',
			'wp-util',
			'wp-i18n',
			'wp-hooks',
			'wp-element',
			'wp-data',
			'wp-api-fetch',
			'wp-polyfill',
			'wp-dom-ready',
			'regenerator-runtime',
			'react',
			'react-dom',
			'wp-editor',
			'wp-blocks',
			'wp-block-library',
		);
	}
}
