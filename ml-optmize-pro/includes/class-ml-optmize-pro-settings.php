<?php
/**
 * Settings — gerenciador unico de configuracoes com defaults premium.
 *
 * Toda a configuracao do plugin fica em uma unica option serializada. Os modulos
 * consomem via get()/get_section() com cache em memoria. Defaults sao calibrados
 * para entregar 80% de otimizacao ao ativar (padrao WP Rocket style).
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Settings {

	const OPTION_KEY = 'ml_optimize_pro_settings';

	/**
	 * Cache em memoria da opcao.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults completos: cada chave que qualquer modulo pode ler.
	 *
	 * Calibrado para entregar 80% de otimizacao ao ativar (padrao WP Rocket).
	 * Modulos sao opt-in, mas vem com valores saudaveis.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(

			// === CACHE ===
			'cache_enabled'           => 1,
			'cache_mobile'            => 1,
			'cache_logged_users'      => 0,
			'cache_lifespan'          => 10 * HOUR_IN_SECONDS,
			'cache_preload'           => 1,
			'cache_preload_interval'  => 'daily',
			'cache_purge_on_update'   => 1,
			'cache_excluded_urls'     => array( '/cart', '/checkout', '/my-account', '/wp-admin', '/wp-login.php' ),

			// === BROWSER CACHE ===
			'browser_cache_enabled'   => 1,
			'browser_cache_expiry'    => 365 * DAY_IN_SECONDS,

			// === ASSETS / FILE OPTIMIZATION ===
			'minify_html'             => 1,
			'minify_css'              => 1,
			'minify_js'               => 1,
			'combine_css'             => 0,
			'combine_js'              => 0,
			'remove_unused_css'       => 0,
			'unused_css_method'       => 'async', // async | remove
			'unused_css_safelist'     => '',
			'defer_js'                => 1,
			'delay_js'                => 0,
			'delay_js_exclusions'     => '',
			'load_css_async'          => 0,

			// === LAZY LOAD ===
			'lazy_images'             => 1,
			'lazy_iframes'            => 1,
			'lazy_videos'             => 1,
			'lazy_bg_images'          => 0,
			'lazy_youtube'            => 1,
			'lazy_skip_first'         => 1,
			'lazy_exclusions'         => '',

			// === LAZY RENDER ===
			'lazy_render_enabled'     => 0,
			'lazy_render_selectors'   => "#comments\n#footer\n.site-sidebar\n.related-posts\n.widget-area\n.woocommerce-related",

			// === SCRIPT MANAGER (Perfmatters style) ===
			'script_manager_enabled'  => 0,
			'script_manager_test_mode'=> 1,
			'script_manager_mu_mode'  => 0,

			// === BLOAT REMOVER ===
			'bloat_emojis'            => 1,
			'bloat_embeds'            => 1,
			'bloat_dashicons'         => 1,
			'bloat_jquery_migrate'   => 1,
			'bloat_xmlrpc'            => 1,
			'bloat_query_strings'     => 1,
			'bloat_wp_version'        => 1,
			'bloat_rsd_link'          => 1,
			'bloat_shortlink'         => 1,
			'bloat_rss_feeds'         => 0,
			'bloat_rest_api_users'    => 0,
			'bloat_block_library'     => 0,
			'bloat_gutenberg_frontend'=> 0,
			'bloat_woocommerce_bloat' => 1,
			'bloat_google_maps'       => 0,
			'bloat_cf7'               => 0,
			'bloat_gravityforms'      => 0,

			// === FONTS ===
			'fonts_self_host'         => 1,
			'fonts_combine'           => 1,
			'fonts_preload'           => 1,
			'fonts_display_swap'      => 1,
			'fonts_system_first'      => 0,
			'fonts_preload_urls'      => '',

			// === PRELOAD / RESOURCE HINTS ===
			'preload_enabled'         => 1,
			'preconnect_enabled'      => 1,
			'dns_prefetch_enabled'    => 1,
			'dns_prefetch_hosts'      => "//fonts.googleapis.com\n//fonts.gstatic.com\n//www.google-analytics.com\n//www.googletagmanager.com",
			'preconnect_hosts'        => "//fonts.gstatic.com\n//cdn.jsdelivr.net",
			'critical_resources'      => '',

			// === SPECULATION RULES (link preload) ===
			'speculation_enabled'     => 0,
			'speculation_eagerness'   => 'moderate', // immediate | moderate | conservative

			// === IMAGES ===
			'images_add_dimensions'   => 1,
			'images_fetchpriority'    => 1,
			'images_avif_webp'        => 1,

			// === DATABASE ===
			'db_cleanup_enabled'      => 1,
			'db_cleanup_schedule'     => 'weekly',
			'db_cleanup_revisions'    => 1,
			'db_cleanup_autodrafts'   => 1,
			'db_cleanup_trash'        => 1,
			'db_cleanup_spam'         => 1,
			'db_cleanup_transients'   => 1,
			'db_cleanup_optimize'     => 0,
			'db_revisions_limit'      => 5,

			// === HEARTBEAT ===
			'heartbeat_frontend'      => 0,
			'heartbeat_backend'       => 1,
			'heartbeat_editor'        => 0,
			'heartbeat_frequency'     => 60,

			// === CDN ===
			'cdn_enabled'             => 0,
			'cdn_url'                 => '',
			'cdn_excludes'            => array( 'wp-content/uploads', 'wp-includes' ),

			// === UPDATER / GITHUB ===
			'updater_github_user'     => 'mlopesdesign',
			'updater_github_repo'     => 'ml-optmize-pro',

			// === META ===
			'settings_version'        => ML_OPTIMIZE_PRO_VERSION,
			'installed_at'            => 0,
			'first_activation_done'   => 0,
		);
	}

	/**
	 * Retorna a configuracao completa (merge de defaults + saved).
	 *
	 * @param bool $force_reload Forcar recarga do banco.
	 * @return array
	 */
	public static function all( $force_reload = false ) {
		if ( null === self::$cache || $force_reload ) {
			$saved   = get_option( self::OPTION_KEY, array() );
			$default = self::defaults();
			if ( ! is_array( $saved ) ) {
				$saved = array();
			}
			self::$cache = array_merge( $default, $saved );
		}
		return self::$cache;
	}

	/**
	 * Retorna uma chave individual.
	 *
	 * @param string $key     Chave.
	 * @param mixed  $default Default se nao existir.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Retorna uma secao logica (subconjunto).
	 *
	 * @param string $section Prefixo da secao (ex: 'cache_' retorna todas as chaves cache_*).
	 * @return array
	 */
	public static function get_section( $section ) {
		$all    = self::all();
		$result = array();
		$len    = strlen( $section );
		foreach ( $all as $key => $value ) {
			if ( 0 === strpos( $key, $section ) ) {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Verifica se uma chave booleana esta ativa.
	 *
	 * @param string $key Chave.
	 * @return bool
	 */
	public static function is_on( $key ) {
		return (bool) self::get( $key, 0 );
	}

	/**
	 * Salva a configuracao completa (substitui).
	 *
	 * @param array $data Configuracao.
	 * @return bool
	 */
	public static function save( array $data ) {
		$current = self::all();
		$merged  = array_merge( $current, $data );
		$merged['settings_version'] = ML_OPTIMIZE_PRO_VERSION;
		$result = update_option( self::OPTION_KEY, $merged, false );
		self::$cache = $merged;
		return $result;
	}

	/**
	 * Salva uma chave individual.
	 *
	 * @param string $key   Chave.
	 * @param mixed  $value Valor.
	 * @return bool
	 */
	public static function set( $key, $value ) {
		return self::save( array( $key => $value ) );
	}

	/**
	 * Reseta para defaults.
	 *
	 * @return bool
	 */
	public static function reset() {
		$defaults            = self::defaults();
		$defaults['settings_version'] = ML_OPTIMIZE_PRO_VERSION;
		$result              = update_option( self::OPTION_KEY, $defaults, false );
		self::$cache         = $defaults;
		return $result;
	}

	/**
	 * Exporta config para JSON (para backup / restore).
	 *
	 * @return string JSON.
	 */
	public static function export_json() {
		$payload = array(
			'plugin'    => 'ml-optmize-pro',
			'version'   => ML_OPTIMIZE_PRO_VERSION,
			'exported'  => current_time( 'mysql' ),
			'site_url'  => home_url( '/' ),
			'settings'  => self::all(),
		);
		return wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Importa config de JSON.
	 *
	 * @param string $json JSON.
	 * @return bool|WP_Error
	 */
	public static function import_json( $json ) {
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['settings'] ) || ! is_array( $decoded['settings'] ) ) {
			return new WP_Error( 'ml_optimize_pro_import_invalid', __( 'JSON invalido.', 'ml-optmize-pro' ) );
		}
		// Filtra apenas chaves conhecidas para evitar injecao.
		$defaults = self::defaults();
		$clean    = array();
		foreach ( $decoded['settings'] as $key => $value ) {
			if ( array_key_exists( $key, $defaults ) ) {
				$clean[ $key ] = $value;
			}
		}
		$clean['settings_version'] = ML_OPTIMIZE_PRO_VERSION;
		update_option( self::OPTION_KEY, $clean, false );
		self::$cache = $clean;
		return true;
	}
}
