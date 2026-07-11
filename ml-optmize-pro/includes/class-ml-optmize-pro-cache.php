<?php
/**
 * Cache — page cache em disco (HTML estatico) com mobile separado.
 *
 * Estrategia:
 * - buffer de saida em template_redirect (front-end)
 * - mobile cache separado via wp_is_mobile() se habilitado
 * - exclusao de URLs via filter (carrinho, checkout, conta)
 * - bypass para usuarios logados (se configurado)
 * - lifespan configuravel com auto-purge
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Cache {

	const CACHE_GROUP = 'ml_optpro_cache';
	const CACHE_FILE_EXT = '.html';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		// Se admin, nao cacheia.
		if ( is_admin() ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 1 );
		add_filter( 'ml_optimize_pro_cache_skip', array( __CLASS__, 'default_skip_rules' ), 5 );
	}

	/**
	 * Decide se deve cachear a request atual.
	 *
	 * @return bool
	 */
	public static function should_cache() {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'cache_enabled' ) ) {
			return false;
		}
		if ( ( ! ML_Optimize_Pro_Settings::is_on( 'cache_logged_users' ) ) && is_user_logged_in() ) {
			return false;
		}
		// So GET, sem query string relevante, sem POST.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return false;
		}
		if ( ! empty( $_GET ) ) {
			// Exclui parametros UTM e afins — cacheia mesmo assim na maioria dos casos.
			// Mas exclui previews e feeds.
			$blocked = array( 'preview', 'preview_id', 'preview_nonce', 'feed', 's' );
			foreach ( $blocked as $key ) {
				if ( isset( $_GET[ $key ] ) ) {
					return false;
				}
			}
		}
		/**
		 * Filter para outras regras de skip.
		 *
		 * @param bool $skip Se true, NAO cacheia.
		 */
		if ( apply_filters( 'ml_optimize_pro_cache_skip', false ) ) {
			return false;
		}
		// Bypass robots / preview / etc.
		if ( is_preview() || is_feed() || is_robots() || is_trackback() ) {
			return false;
		}
		// URL excluidas pela config.
		$excluded = (array) ML_Optimize_Pro_Settings::get( 'cache_excluded_urls', array() );
		if ( self::url_in_excluded_list( $excluded ) ) {
			return false;
		}
		// Nao cacheia paginas de erro 404.
		if ( is_404() ) {
			return false;
		}
		return true;
	}

	/**
	 * Excluded URLs padrao.
	 *
	 * @param bool $skip Default false.
	 * @return bool
	 */
	public static function default_skip_rules( $skip ) {
		// Nao cacheia AJAX, REST, cron, instalacao.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return true;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}
		if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		return $skip;
	}

	/**
	 * Verifica se a URL atual bate com alguma da lista de exclusao.
	 *
	 * @param array $patterns Patterns.
	 * @return bool
	 */
	private static function url_in_excluded_list( $patterns ) {
		if ( empty( $patterns ) ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( ! $uri ) {
			return false;
		}
		foreach ( $patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}
			if ( false !== strpos( $uri, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Inicia buffer se aplicavel.
	 */
	public static function maybe_start_buffer() {
		if ( ! self::should_cache() ) {
			return;
		}
		$key = self::cache_key();
		$file = self::cache_file( $key );
		if ( file_exists( $file ) ) {
			$mtime = (int) filemtime( $file );
			$lifespan = (int) ML_Optimize_Pro_Settings::get( 'cache_lifespan', 10 * HOUR_IN_SECONDS );
			if ( $lifespan > 0 && ( time() - $mtime ) < $lifespan ) {
				// Serve cache.
				header( 'X-ML-Optimize-Pro-Cache: HIT' );
				header( 'X-ML-Optimize-Pro-Cache-Key: ' . sanitize_key( $key ) );
				readfile( $file );
				exit;
			}
			// Expirado.
			@unlink( $file );
		}
		header( 'X-ML-Optimize-Pro-Cache: MISS' );
		ob_start( array( __CLASS__, 'on_buffer_end' ) );
	}

	/**
	 * Callback de fim de buffer — salva HTML no cache.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function on_buffer_end( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 255 ) {
			return $html;
		}
		// So cacheia 200 OK.
		if ( function_exists( 'http_response_code' ) && 200 !== http_response_code() ) {
			return $html;
		}
		$key = self::cache_key();
		$file = self::cache_file( $key );
		$dir = dirname( $file );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		// Comentario marcador no HTML (ajuda debug).
		$marker = '<!-- ML Optimize Pro cache: ' . esc_attr( $key ) . ' @ ' . current_time( 'mysql' ) . ' -->';
		$html = $html . "\n" . $marker;
		@file_put_contents( $file, $html );
		return $html;
	}

	/**
	 * Calcula chave de cache da request atual.
	 *
	 * @return string
	 */
	public static function cache_key() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$uri  = strtok( $uri, '?' );
		$uri  = trim( $uri, '/' );
		$uri  = '' === $uri ? '_root_' : $uri;
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$device = ( ML_Optimize_Pro_Settings::is_on( 'cache_mobile' ) && wp_is_mobile() ) ? 'mobile' : 'desktop';
		return $host . '__' . $device . '__' . md5( $uri );
	}

	/**
	 * Path do arquivo de cache para a chave.
	 *
	 * @param string $key Chave.
	 * @return string
	 */
	public static function cache_file( $key ) {
		$dir = ML_OPTIMIZE_PRO_CACHE_DIR . 'pages/' . substr( $key, 0, 2 ) . '/';
		return $dir . $key . self::CACHE_FILE_EXT;
	}

	/**
	 * Limpa todo o cache.
	 *
	 * @return int Quantos arquivos removidos.
	 */
	public static function purge_all() {
		$dir = ML_OPTIMIZE_PRO_CACHE_DIR . 'pages/';
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		return self::rmdir_recursive( $dir );
	}

	/**
	 * Limpa URL especifica.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function purge_url( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}
		$path  = trim( $path, '/' );
		$path  = '' === $path ? '_root_' : $path;
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$count = 0;
		foreach ( array( 'desktop', 'mobile' ) as $device ) {
			$key  = $host . '__' . $device . '__' . md5( $path );
			$file = self::cache_file( $key );
			if ( file_exists( $file ) ) {
				@unlink( $file );
				$count++;
			}
		}
		return $count > 0;
	}

	/**
	 * Limpa cache por hook de acao do WP (post update, comment, etc).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function purge_on_post_change( $post_id ) {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'cache_purge_on_update' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$url = get_permalink( $post_id );
		if ( $url ) {
			self::purge_url( $url );
		}
		// Limpa home e archives.
		self::purge_url( home_url( '/' ) );
		if ( function_exists( 'get_post_type_archive_link' ) ) {
			$pt = get_post_type( $post_id );
			if ( $pt ) {
				$link = get_post_type_archive_link( $pt );
				if ( $link ) {
					self::purge_url( $link );
				}
			}
		}
		ML_Optimize_Pro_Logs::add( 'cache', sprintf( 'Cache purgado para post #%d', $post_id ), 'info' );
	}

	/**
	 * Remove recursivamente.
	 *
	 * @param string $dir Diretorio.
	 * @return int Total removido.
	 */
	private static function rmdir_recursive( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		$count = 0;
		$items = @scandir( $dir );
		if ( ! is_array( $items ) ) {
			return 0;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				$count += self::rmdir_recursive( $path );
			} else {
				if ( @unlink( $path ) ) {
					$count++;
				}
			}
		}
		@rmdir( $dir );
		return $count;
	}
}
