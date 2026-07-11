<?php
/**
 * REST API — endpoints internos para o admin checar status do plugin.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_REST_API {

	const NAMESPACE = 'ml-optimize-pro/v1';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registra rotas.
	 */
	public static function register_routes() {
		register_rest_route( self::NAMESPACE, '/status', array(
			'methods'             => 'GET',
			'permission_callback' => array( __CLASS__, 'check_permission' ),
			'callback'            => array( __CLASS__, 'handle_status' ),
		) );
		register_rest_route( self::NAMESPACE, '/score', array(
			'methods'             => 'GET',
			'permission_callback' => array( __CLASS__, 'check_permission' ),
			'callback'            => array( __CLASS__, 'handle_score' ),
		) );
		register_rest_route( self::NAMESPACE, '/cache/purge', array(
			'methods'             => 'POST',
			'permission_callback' => array( __CLASS__, 'check_permission' ),
			'callback'            => array( __CLASS__, 'handle_cache_purge' ),
		) );
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public static function check_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * /status.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_status() {
		$modules = ML_Optimize_Pro_Performance_Hub::get_active_modules();
		$db      = ML_Optimize_Pro_Performance_Hub::get_db_stats();
		$score   = ML_Optimize_Pro_Performance_Hub::get_score();
		$logs    = ML_Optimize_Pro_Logs::get( 20 );
		return rest_ensure_response( array(
			'version'  => ML_OPTIMIZE_PRO_VERSION,
			'php'      => PHP_VERSION,
			'wp'       => get_bloginfo( 'version' ),
			'server'   => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			'modules'  => $modules,
			'score'    => $score,
			'db'       => $db,
			'logs'     => $logs,
			'time'     => current_time( 'mysql' ),
			'timestamp'=> time(),
		) );
	}

	/**
	 * /score.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_score() {
		return rest_ensure_response( ML_Optimize_Pro_Performance_Hub::get_score() );
	}

	/**
	 * /cache/purge.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_cache_purge() {
		$count = ML_Optimize_Pro_Cache::purge_all();
		ML_Optimize_Pro_Logs::add( 'cache', sprintf( 'Cache purgado manualmente: %d arquivos', $count ), 'info' );
		return rest_ensure_response( array(
			'purged' => $count,
		) );
	}
}
