<?php
/**
 * Cache Preload — pre-aquece o cache usando sitemap + home + principais URLs.
 *
 * Hooks: robots.txt (adiciona regras de preload), wp_loaded (dispara preload inicial
 * apos atualizacoes de post), cron (preload periodico).
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Cache_Preload {

	const CACHE_KEY_PROGRESS = 'ml_optimize_pro_preload_progress';
	const MAX_URLS_PER_RUN   = 200;

	/**
	 * Registra hooks.
	 */
	public static function register() {
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_preload_on_update' ) );
		add_action( 'save_post', array( __CLASS__, 'schedule_quick_preload' ), 20, 3 );
	}

	/**
	 * Hook save_post — agenda preload rapido.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @param bool    $update  Update ou create.
	 */
	public static function schedule_quick_preload( $post_id, $post, $update ) {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'cache_preload' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		// Cooldown de 30s para nao flood.
		$lock = get_transient( 'ml_optimize_pro_preload_lock' );
		if ( $lock ) {
			return;
		}
		set_transient( 'ml_optimize_pro_preload_lock', 1, 30 );
		// Agenda via single event 5s no futuro.
		wp_schedule_single_event( time() + 5, 'ml_optimize_pro_preload_event' );
	}

	/**
	 * Dispara preload apos update de post (hook wp_loaded).
	 */
	public static function maybe_preload_on_update() {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'cache_preload' ) ) {
			return;
		}
		// Apenas em admin.
		if ( ! is_admin() ) {
			return;
		}
		$lock = get_transient( 'ml_optimize_pro_preload_lock' );
		if ( $lock ) {
			return;
		}
		// Hook em acoes de purge ja dispara schedule.
	}

	/**
	 * Cron handler.
	 */
	public static function run_cron() {
		$urls = self::collect_urls();
		if ( empty( $urls ) ) {
			return;
		}
		$progress = array(
			'total'   => count( $urls ),
			'done'    => 0,
			'started' => time(),
			'urls'    => $urls,
		);
		set_transient( self::CACHE_KEY_PROGRESS, $progress, HOUR_IN_SECONDS );
		// Faz warmup em batch (max MAX_URLS_PER_RUN).
		$urls = array_slice( $urls, 0, self::MAX_URLS_PER_RUN );
		$count = self::warmup( $urls );
		ML_Optimize_Pro_Logs::add( 'cache_preload', sprintf( 'Preload concluido: %d/%d URLs', $count, count( $urls ) ), 'success' );
		update_option( 'ml_optimize_pro_last_preload', current_time( 'mysql' ), false );
	}

	/**
	 * Coleta URLs para preload: home + sitemap + posts recentes.
	 *
	 * @return array
	 */
	public static function collect_urls() {
		$urls = array();
		// Home.
		$urls[] = home_url( '/' );
		// Blog page se existir.
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page ) {
			$link = get_permalink( $posts_page );
			if ( $link ) {
				$urls[] = $link;
			}
		}
		// Sitemap (wp_sitemap).
		$sitemap = home_url( '/wp-sitemap.xml' );
		if ( $sitemap ) {
			$urls[] = $sitemap;
		}
		// Posts recentes (ultimos 50 publicados).
		$recent = get_posts( array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );
		foreach ( $recent as $post ) {
			$url = get_permalink( $post );
			if ( $url ) {
				$urls[] = $url;
			}
		}
		// Paginas principais.
		$front = (int) get_option( 'page_on_front' );
		if ( $front ) {
			$urls[] = get_permalink( $front );
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Faz warmup chamando cada URL e salvando no cache.
	 *
	 * @param array $urls URLs.
	 * @return int Total cacheado com sucesso.
	 */
	public static function warmup( $urls ) {
		$count = 0;
		foreach ( $urls as $url ) {
			$response = wp_remote_get( $url, array(
				'timeout'   => 15,
				'sslverify' => apply_filters( 'ml_optimize_pro_preload_sslverify', false ),
				'headers'   => array(
					'User-Agent' => 'ML-Optimize-Pro-Preload/' . ML_OPTIMIZE_PRO_VERSION,
				),
			) );
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Retorna progresso atual.
	 *
	 * @return array
	 */
	public static function get_progress() {
		$progress = get_transient( self::CACHE_KEY_PROGRESS );
		return is_array( $progress ) ? $progress : array();
	}
}
