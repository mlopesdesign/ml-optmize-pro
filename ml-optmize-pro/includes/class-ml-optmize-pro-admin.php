<?php
/**
 * Admin — menu, submenus, enqueue, handlers de form e AJAX.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Admin {

	const MENU_SLUG = 'ml-optmize-pro';
	const NONCE_ACTION = 'ml_optimize_pro_admin';
	const NONCE_FIELD  = '_mlopt_nonce';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'wp_ajax_ml_optimize_pro_save_module', array( __CLASS__, 'ajax_save_module' ) );
		add_action( 'wp_ajax_ml_optimize_pro_save_setting', array( __CLASS__, 'ajax_save_setting' ) );
		add_action( 'wp_ajax_ml_optimize_pro_save_script_rule', array( __CLASS__, 'ajax_save_script_rule' ) );
		add_action( 'wp_ajax_ml_optimize_pro_delete_script_rule', array( __CLASS__, 'ajax_delete_script_rule' ) );
		add_action( 'wp_ajax_ml_optimize_pro_purge_cache', array( __CLASS__, 'ajax_purge_cache' ) );
		add_action( 'wp_ajax_ml_optimize_pro_run_db_cleanup', array( __CLASS__, 'ajax_run_db_cleanup' ) );
		add_action( 'wp_ajax_ml_optimize_pro_apply_browser_cache', array( __CLASS__, 'ajax_apply_browser_cache' ) );
		add_action( 'wp_ajax_ml_optimize_pro_reset_settings', array( __CLASS__, 'ajax_reset_settings' ) );
		add_action( 'wp_ajax_ml_optimize_pro_export_settings', array( __CLASS__, 'ajax_export_settings' ) );
		add_action( 'wp_ajax_ml_optimize_pro_import_settings', array( __CLASS__, 'ajax_import_settings' ) );
		add_action( 'wp_ajax_ml_optimize_pro_clear_logs', array( __CLASS__, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_ml_optimize_pro_check_update', array( __CLASS__, 'ajax_check_update' ) );
		add_action( 'wp_ajax_ml_optimize_pro_get_enqueued', array( __CLASS__, 'ajax_get_enqueued' ) );
		// Cache purge on post change.
		add_action( 'save_post', array( 'ML_Optimize_Pro_Cache', 'purge_on_post_change' ), 20, 2 );
		add_action( 'comment_post', array( __CLASS__, 'purge_on_comment' ) );
		// Plugin action links.
		add_filter( 'plugin_action_links_' . ML_OPTIMIZE_PRO_BASENAME, array( __CLASS__, 'plugin_action_links' ) );
	}

	/**
	 * Adiciona link "Settings" no plugin list.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		$settings     = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Configuracoes', 'ml-optmize-pro' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}

	/**
	 * Registra o menu.
	 */
	public static function register_menu() {
		add_menu_page(
			esc_html__( 'ML Optimize Pro', 'ml-optmize-pro' ),
			esc_html__( 'ML Optimize Pro', 'ml-optmize-pro' ),
			'manage_options',
			self::MENU_SLUG,
			array( 'ML_Optimize_Pro_Admin_Page', 'render_tab_router' ),
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#a7aaad"><path d="M11 2L4 11h5l-1 7 7-9h-5l1-7z"/></svg>' ),
			59
		);
		$tabs = array(
			'cache'         => __( 'Cache', 'ml-optmize-pro' ),
			'files'         => __( 'File Optimization', 'ml-optmize-pro' ),
			'lazy'          => __( 'Lazy Load', 'ml-optmize-pro' ),
			'script_manager'=> __( 'Script Manager', 'ml-optmize-pro' ),
			'bloat'         => __( 'Bloat Remover', 'ml-optmize-pro' ),
			'fonts'         => __( 'Fonts', 'ml-optmize-pro' ),
			'preload'       => __( 'Preload & Hints', 'ml-optmize-pro' ),
			'database'      => __( 'Database', 'ml-optmize-pro' ),
			'heartbeat'     => __( 'Heartbeat', 'ml-optmize-pro' ),
			'cdn'           => __( 'CDN', 'ml-optmize-pro' ),
			'speculation'   => __( 'Speculation', 'ml-optmize-pro' ),
			'logs'          => __( 'Logs', 'ml-optmize-pro' ),
			'settings'      => __( 'Settings', 'ml-optmize-pro' ),
		);
		foreach ( $tabs as $tab_slug => $label ) {
			add_submenu_page(
				self::MENU_SLUG,
				$label,
				$label,
				'manage_options',
				self::MENU_SLUG . '-' . $tab_slug,
				array( 'ML_Optimize_Pro_Admin_Page', 'render_tab_router' )
			);
		}
	}

	/**
	 * Enfileira assets admin.
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue_assets( $hook ) {
		// Enfileira em QUALQUER pagina admin relacionada ao plugin
		// (o filtro strpos estava bloqueando indevidamente o carregamento
		// do CSS, deixando o admin sem identidade visual). Custo: ~13 KB
		// em todas as paginas admin (irrelevante).
		$global_handle = 'ml-optmize-pro-admin';
		wp_enqueue_style(
			$global_handle,
			ML_OPTIMIZE_PRO_URL . 'assets/admin.css',
			array(),
			ML_OPTIMIZE_PRO_VERSION
		);
		wp_enqueue_script(
			$global_handle,
			ML_OPTIMIZE_PRO_URL . 'assets/admin.js',
			array( 'jquery' ),
			ML_OPTIMIZE_PRO_VERSION,
			true
		);
		wp_localize_script( $global_handle, 'mlopt', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
			'restUrl'  => rest_url( 'ml-optimize-pro/v1/' ),
			'restNonce'=> wp_create_nonce( 'wp_rest' ),
			'strings'  => array(
				'confirmReset'  => __( 'Tem certeza? Isso restaura todos os defaults.', 'ml-optmize-pro' ),
				'confirmPurge'  => __( 'Purgar todo o cache?', 'ml-optmize-pro' ),
				'confirmDelete' => __( 'Excluir esta regra?', 'ml-optmize-pro' ),
				'saving'        => __( 'Salvando...', 'ml-optmize-pro' ),
				'saved'         => __( 'Salvo', 'ml-optmize-pro' ),
				'error'         => __( 'Erro', 'ml-optmize-pro' ),
			),
		) );

	}

	/**
	 * Handlers de form (POST tradicional).
	 */
	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		// Resposta: redirect com flag.
		$redirect = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		if ( 'save_general' === $action ) {
			// Salva chaves individuais vindas do form.
			$keys = array(
				'cdn_url',
				'fonts_preload_urls',
				'critical_resources',
				'preconnect_hosts',
				'dns_prefetch_hosts',
				'delay_js_exclusions',
				'unused_css_safelist',
				'lazy_render_selectors',
				'lazy_exclusions',
				'cache_excluded_urls',
				'cdn_excludes',
				'speculation_eagerness',
				'cache_lifespan',
				'browser_cache_expiry',
				'heartbeat_frequency',
				'db_revisions_limit',
				'updater_github_user',
				'updater_github_repo',
			);
			$update = array();
			foreach ( $keys as $key ) {
				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}
				$raw = wp_unslash( $_POST[ $key ] );
				if ( is_array( $raw ) ) {
					$update[ $key ] = array_map( 'sanitize_text_field', $raw );
				} elseif ( in_array( $key, array( 'cache_lifespan', 'browser_cache_expiry', 'heartbeat_frequency', 'db_revisions_limit' ), true ) ) {
					$update[ $key ] = max( 0, (int) $raw );
				} else {
					$update[ $key ] = sanitize_textarea_field( $raw );
				}
			}
			ML_Optimize_Pro_Settings::save( $update );
			$redirect = add_query_arg( array( 'tab' => 'settings', 'saved' => 1 ), $redirect );
		}
		if ( 'save_script_rules' === $action ) {
			$rules_json = isset( $_POST['rules_json'] ) ? wp_unslash( $_POST['rules_json'] ) : '[]';
			$rules      = json_decode( $rules_json, true );
			if ( is_array( $rules ) ) {
				ML_Optimize_Pro_Script_Manager::save_rules( $rules );
			}
			$redirect = add_query_arg( array( 'tab' => 'script_manager', 'saved' => 1 ), $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Purga cache via AJAX.
	 */
	public static function ajax_purge_cache() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$count = ML_Optimize_Pro_Cache::purge_all();
		ML_Optimize_Pro_Logs::add( 'cache', sprintf( 'Cache purgado via AJAX: %d arquivos', $count ), 'info' );
		wp_send_json_success( array( 'purged' => $count ) );
	}

	/**
	 * Roda DB cleanup.
	 */
	public static function ajax_run_db_cleanup() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$stats = ML_Optimize_Pro_Database::run( 'manual' );
		ML_Optimize_Pro_Logs::add( 'database', sprintf( 'Cleanup manual: %s', wp_json_encode( $stats ) ), 'success' );
		update_option( 'ml_optimize_pro_last_cleanup', current_time( 'mysql' ), false );
		wp_send_json_success( array( 'stats' => $stats ) );
	}

	/**
	 * Aplica regras de browser cache.
	 */
	public static function ajax_apply_browser_cache() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$result = ML_Optimize_Pro_Browser_Cache::apply();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'applied' => true ) );
	}

	/**
	 * Reseta settings.
	 */
	public static function ajax_reset_settings() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		ML_Optimize_Pro_Settings::reset();
		ML_Optimize_Pro_Logs::add( 'settings', 'Settings resetados para defaults.', 'warning' );
		wp_send_json_success();
	}

	/**
	 * Export.
	 */
	public static function ajax_export_settings() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		wp_send_json_success( array( 'json' => ML_Optimize_Pro_Settings::export_json() ) );
	}

	/**
	 * Import.
	 */
	public static function ajax_import_settings() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$json = isset( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : '';
		$result = ML_Optimize_Pro_Settings::import_json( $json );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
	}

	/**
	 * Save single setting via AJAX.
	 */
	public static function ajax_save_setting() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$key   = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$value = isset( $_POST['value'] );
		if ( ! $key ) {
			wp_send_json_error( array( 'message' => 'missing_key' ) );
		}
		$raw = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		// Se for booleano, vira 0/1.
		$allowed = array_keys( ML_Optimize_Pro_Settings::defaults() );
		if ( ! in_array( $key, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => 'invalid_key' ) );
		}
		ML_Optimize_Pro_Settings::set( $key, $raw );
		wp_send_json_success();
	}

	/**
	 * Save modulos inteiros via AJAX.
	 */
	public static function ajax_save_module() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$module  = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		$payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '{}';
		$payload = json_decode( $payload, true );
		if ( ! is_array( $payload ) ) {
			wp_send_json_error( array( 'message' => 'invalid_payload' ) );
		}
		// Sanitiza e filtra.
		$allowed = array_keys( ML_Optimize_Pro_Settings::defaults() );
		$clean   = array();
		foreach ( $payload as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$clean[ $key ] = array_map( 'sanitize_text_field', $value );
			} elseif ( is_bool( $value ) ) {
				$clean[ $key ] = $value ? 1 : 0;
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		ML_Optimize_Pro_Settings::save( $clean );
		ML_Optimize_Pro_Logs::add( 'admin', sprintf( 'Modulo %s salvo.', $module ), 'info' );
		wp_send_json_success();
	}

	/**
	 * Save rule do script manager.
	 */
	public static function ajax_save_script_rule() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$rules = ML_Optimize_Pro_Script_Manager::get_rules();
		$index = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;
		$rule  = isset( $_POST['rule'] ) ? json_decode( wp_unslash( $_POST['rule'] ), true ) : null;
		if ( ! is_array( $rule ) ) {
			wp_send_json_error( array( 'message' => 'invalid_rule' ) );
		}
		if ( $index >= 0 && isset( $rules[ $index ] ) ) {
			$rules[ $index ] = $rule;
		} else {
			$rules[] = $rule;
		}
		ML_Optimize_Pro_Script_Manager::save_rules( $rules );
		wp_send_json_success();
	}

	/**
	 * Delete rule.
	 */
	public static function ajax_delete_script_rule() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$rules = ML_Optimize_Pro_Script_Manager::get_rules();
		$index = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;
		if ( $index >= 0 && isset( $rules[ $index ] ) ) {
			unset( $rules[ $index ] );
			$rules = array_values( $rules );
			ML_Optimize_Pro_Script_Manager::save_rules( $rules );
		}
		wp_send_json_success();
	}

	/**
	 * Limpa logs.
	 */
	public static function ajax_clear_logs() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		ML_Optimize_Pro_Logs::clear();
		wp_send_json_success();
	}

	/**
	 * Check update.
	 */
	public static function ajax_check_update() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		ML_Optimize_Pro_Updater::clear_cache();
		$release = ML_Optimize_Pro_Updater::fetch_remote();
		if ( ! $release ) {
			wp_send_json_error( array( 'message' => 'fetch_failed' ) );
		}
		wp_send_json_success( array( 'release' => $release ) );
	}

	/**
	 * Pega scripts enfileirados atualmente.
	 */
	public static function ajax_get_enqueued() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : home_url( '/' );
		// Faz request e captura a pagina.
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array( 'User-Agent' => 'ML-OptPro-Probe/' . ML_OPTIMIZE_PRO_VERSION ),
		) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			wp_send_json_success( array( 'scripts' => array(), 'styles' => array() ) );
		}
		preg_match_all( '#<script[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>#i', $body, $js );
		preg_match_all( '#<link[^>]*\bhref=["\']([^"\']+\.css[^"\']*)["\'][^>]*>#i', $body, $css );
		wp_send_json_success( array(
			'scripts' => array_values( array_unique( $js[1] ?? array() ) ),
			'styles'  => array_values( array_unique( $css[1] ?? array() ) ),
		) );
	}

	/**
	 * Purga cache apos comment.
	 */
	public static function purge_on_comment( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( $comment ) {
			ML_Optimize_Pro_Cache::purge_url( get_permalink( $comment->comment_post_ID ) );
		}
	}
}
