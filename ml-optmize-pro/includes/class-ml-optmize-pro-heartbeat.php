<?php
/**
 * Heartbeat Control — desabilita ou reduz frequencia do WP Heartbeat API.
 *
 * - Frontend: desabilitar (0) por padrao.
 * - Backend: permitir, mas com frequencia maior.
 * - Editor: desabilitar para reduzir carga em post types com muitos usuarios.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Heartbeat {

	/**
	 * Registra hooks.
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_modify' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_disable_frontend' ) );
	}

	/**
	 * Decide o que fazer baseado em settings.
	 */
	public static function maybe_modify() {
		if ( ! is_admin() ) {
			return;
		}
		$backend = (bool) ML_Optimize_Pro_Settings::get( 'heartbeat_backend', 1 );
		$editor  = (bool) ML_Optimize_Pro_Settings::get( 'heartbeat_editor', 0 );
		$freq    = (int) ML_Optimize_Pro_Settings::get( 'heartbeat_frequency', 60 );
		$freq    = max( 15, $freq );
		if ( $backend ) {
			// Forca frequencia custom.
			add_filter( 'heartbeat_settings', function( $settings ) use ( $freq ) {
				$settings['interval'] = $freq;
				return $settings;
			} );
		} else {
			// Desabilita em paginas admin nao-edicao.
			add_action( 'admin_init', function() {
				global $pagenow;
				if ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) {
					return;
				}
				wp_deregister_script( 'heartbeat' );
			} );
		}
		if ( ! $editor ) {
			add_action( 'admin_init', function() {
				global $pagenow;
				if ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) {
					wp_deregister_script( 'heartbeat' );
				}
			} );
		}
	}

	/**
	 * Desabilita heartbeat no frontend.
	 */
	public static function maybe_disable_frontend() {
		if ( ! is_admin() && ! ML_Optimize_Pro_Settings::is_on( 'heartbeat_frontend' ) ) {
			wp_deregister_script( 'heartbeat' );
		}
	}
}
