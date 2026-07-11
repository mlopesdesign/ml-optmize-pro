<?php
/**
 * Deactivator — desinstalacao leve: limpa crons mas mantem opcoes e cache (para o user nao perder
 * config ao reativar). O uninstall.php faz a limpeza pesada.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Deactivator {

	/**
	 * Hook de desativacao.
	 */
	public static function deactivate() {
		try {
			self::clear_crons();
			self::flush_rewrite();
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ML Optimize Pro] Deactivation error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Limpa todos os crons do plugin.
	 */
	private static function clear_crons() {
		$hooks = array(
			'ml_optimize_pro_preload_event',
			'ml_optimize_pro_db_cleanup_event',
			'ml_optimize_pro_logs_prune_event',
		);
		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
	}

	/**
	 * Flush rewrite rules (Speculation Rules API).
	 */
	private static function flush_rewrite() {
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
	}
}
