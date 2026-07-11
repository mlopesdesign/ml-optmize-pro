<?php
/**
 * Updater — auto-update via GitHub Releases (padrao simples estilo ml-gallery-pro).
 *
 * Hooks: pre_set_site_transient_update_plugins, plugins_api, upgrader_pre_download.
 * Cache: 6h quando ha versao, 15min em erro. Falha seguro: nao quebra o painel.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Updater {

	const CACHE_KEY_VERSION = 'ml_optimize_pro_update_version';
	const CACHE_KEY_DATA    = 'ml_optimize_pro_update_data';
	const CACHE_TTL_OK      = 6 * HOUR_IN_SECONDS;
	const CACHE_TTL_ERR     = 15 * MINUTE_IN_SECONDS;
	const API_TIMEOUT       = 12;

	/**
	 * Registra hooks do updater.
	 */
	public static function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update' ), 20, 1 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'filter_download_url' ), 10, 3 );
	}

	/**
	 * Endpoint da API do GitHub.
	 *
	 * @return string
	 */
	private static function api_url() {
		$user = ML_Optimize_Pro_Settings::get( 'updater_github_user', ML_OPTIMIZE_PRO_GITHUB_USER );
		$repo = ML_Optimize_Pro_Settings::get( 'updater_github_repo', ML_OPTIMIZE_PRO_GITHUB_REPO );
		$user = $user ? sanitize_text_field( $user ) : ML_OPTIMIZE_PRO_GITHUB_USER;
		$repo = $repo ? sanitize_text_field( $repo ) : ML_OPTIMIZE_PRO_GITHUB_REPO;
		return sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', $user, $repo );
	}

	/**
	 * Faz cache do remote release.
	 *
	 * @return array|null Release data ou null em erro.
	 */
	private static function fetch_remote() {
		$cached = get_transient( self::CACHE_KEY_DATA );
		if ( is_array( $cached ) && ! empty( $cached['tag_name'] ) ) {
			return $cached;
		}
		$response = wp_remote_get( self::api_url(), array(
			'timeout' => self::API_TIMEOUT,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'ml-optmize-pro/' . ML_OPTIMIZE_PRO_VERSION . ';' . home_url(),
			),
		) );
		if ( is_wp_error( $response ) ) {
			set_transient( self::CACHE_KEY_DATA, array( '__error__' => $response->get_error_message() ), self::CACHE_TTL_ERR );
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 200 !== $code || empty( $body ) ) {
			set_transient( self::CACHE_KEY_DATA, array( '__error__' => 'http_' . $code ), self::CACHE_TTL_ERR );
			return null;
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			set_transient( self::CACHE_KEY_DATA, array( '__error__' => 'invalid_payload' ), self::CACHE_TTL_ERR );
			return null;
		}
		set_transient( self::CACHE_KEY_DATA, $data, self::CACHE_TTL_OK );
		return $data;
	}

	/**
	 * Invalida cache (usado em "Check again").
	 */
	public static function clear_cache() {
		delete_transient( self::CACHE_KEY_DATA );
		delete_transient( self::CACHE_KEY_VERSION );
	}

	/**
	 * Hook pre_set_site_transient_update_plugins.
	 *
	 * @param object $transient Transient do WP.
	 * @return object
	 */
	public static function check_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}
		$current = isset( $transient->checked[ ML_OPTIMIZE_PRO_BASENAME ] )
			? $transient->checked[ ML_OPTIMIZE_PRO_BASENAME ]
			: ML_OPTIMIZE_PRO_VERSION;
		$release = self::fetch_remote();
		if ( ! is_array( $release ) || empty( $release['tag_name'] ) || isset( $release['__error__'] ) ) {
			return $transient;
		}
		$remote_version = ltrim( $release['tag_name'], 'v' );
		if ( version_compare( $current, $remote_version, '>=' ) ) {
			return $transient;
		}
		$package = self::find_plugin_zip( $release );
		if ( ! $package ) {
			return $transient;
		}
		$transient->response[ ML_OPTIMIZE_PRO_BASENAME ] = (object) array(
			'slug'        => ML_OPTIMIZE_PRO_SLUG,
			'plugin'      => ML_OPTIMIZE_PRO_BASENAME,
			'new_version' => $remote_version,
			'url'         => isset( $release['html_url'] ) ? esc_url_raw( $release['html_url'] ) : '',
			'package'     => $package,
			'tested'      => isset( $release['target_commitish'] ) ? $release['target_commitish'] : '',
			'icons'       => array(),
		);
		set_transient( self::CACHE_KEY_VERSION, $remote_version, self::CACHE_TTL_OK );
		return $transient;
	}

	/**
	 * Hook plugins_api — modal de "View version X details".
	 *
	 * @param mixed  $api  Default.
	 * @param string $action Action.
	 * @param mixed  $args  Args.
	 * @return mixed
	 */
	public static function plugins_api( $api, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $api;
		}
		if ( ! isset( $args->slug ) || ML_OPTIMIZE_PRO_SLUG !== $args->slug ) {
			return $api;
		}
		$release = self::fetch_remote();
		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			return $api;
		}
		$remote_version = ltrim( $release['tag_name'], 'v' );
		$api            = (object) array(
			'name'              => 'ML Optimize Pro',
			'slug'              => ML_OPTIMIZE_PRO_SLUG,
			'version'           => $remote_version,
			'author'            => '<a href="https://mlopesdesign.com.br">ML Lopes Design</a>',
			'homepage'          => 'https://mlopesdesign.com.br/plugins/ml-optmize-pro',
			'short_description' => 'Suite premium de otimizacao WordPress.',
			'sections'          => array(
				'description' => isset( $release['body'] ) ? wp_kses_post( $release['body'] ) : '',
				'changelog'   => isset( $release['body'] ) ? wp_kses_post( $release['body'] ) : '',
			),
			'download_link'     => self::find_plugin_zip( $release ),
		);
		return $api;
	}

	/**
	 * Bloqueia download de URL que nao seja o asset oficial do release.
	 *
	 * @param mixed  $reply Default.
	 * @param string $package URL do package.
	 * @param object $upgrader Upgrader.
	 * @return mixed
	 */
	public static function filter_download_url( $reply, $package, $upgrader ) {
		if ( empty( $upgrader->skin->plugin ) || ML_OPTIMIZE_PRO_BASENAME !== $upgrader->skin->plugin ) {
			return $reply;
		}
		if ( empty( $package ) ) {
			return $reply;
		}
		$release = self::fetch_remote();
		if ( ! is_array( $release ) ) {
			return $reply;
		}
		$official = self::find_plugin_zip( $release );
		if ( $official && $official === $package ) {
			return $reply;
		}
		// Rejeita zipballs / source archives / outros.
		return new WP_Error( 'ml_optimize_pro_official_only', __( 'Apenas o ZIP oficial anexado ao release pode ser usado.', 'ml-optmize-pro' ) );
	}

	/**
	 * Procura o asset ZIP correto dentro dos assets do release.
	 *
	 * @param array $release Release data.
	 * @return string|null URL do asset ou null se nao achar.
	 */
	private static function find_plugin_zip( array $release ) {
		$expected_name = sprintf( '%s-v%%s.zip', ML_OPTIMIZE_PRO_SLUG );
		$assets        = isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : array();
		// 1. Tenta match exato: ml-optmize-pro-vX.Y.Z.zip
		$version = isset( $release['tag_name'] ) ? ltrim( $release['tag_name'], 'v' ) : '';
		if ( $version ) {
			$target = sprintf( $expected_name, $version );
			foreach ( $assets as $asset ) {
				if ( isset( $asset['name'] ) && $asset['name'] === $target && ! empty( $asset['browser_download_url'] ) ) {
					return esc_url_raw( $asset['browser_download_url'] );
				}
			}
		}
		// 2. Fallback: qualquer asset com nome contendo o slug.
		foreach ( $assets as $asset ) {
			if ( isset( $asset['name'] ) && false !== strpos( $asset['name'], ML_OPTIMIZE_PRO_SLUG ) && false !== strpos( $asset['name'], '.zip' ) ) {
				if ( ! empty( $asset['browser_download_url'] ) ) {
					return esc_url_raw( $asset['browser_download_url'] );
				}
			}
		}
		return null;
	}
}
