<?php
/**
 * Logs — sistema leve de logs in-memory + transient, usado por todos os modulos.
 *
 * Mantem 200 entradas max em transient, com auto-prune diario via cron. Nao usa
 * tabela custom (zero impacto no banco).
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Logs {

	const OPTION_KEY = 'ml_optimize_pro_logs';
	const MAX_ENTRIES = 200;

	/**
	 * Adiciona entrada no log.
	 *
	 * @param string $module  Modulo (cache, fonts, bloat, etc.).
	 * @param string $message Mensagem.
	 * @param string $level   info | warning | error | success.
	 */
	public static function add( $module, $message, $level = 'info' ) {
		$logs   = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		$entry = array(
			'time'    => current_time( 'mysql' ),
			'ts'      => time(),
			'module'  => sanitize_key( $module ),
			'level'   => in_array( $level, array( 'info', 'warning', 'error', 'success' ), true ) ? $level : 'info',
			'message' => wp_kses_post( $message ),
		);
		array_unshift( $logs, $entry );
		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		}
		update_option( self::OPTION_KEY, $logs, false );
	}

	/**
	 * Retorna logs.
	 *
	 * @param int    $limit Limite (mais recentes primeiro).
	 * @param string $level Filtrar por nivel (vazio = todos).
	 * @return array
	 */
	public static function get( $limit = 50, $level = '' ) {
		$logs = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			return array();
		}
		if ( $level ) {
			$logs = array_values( array_filter( $logs, function( $entry ) use ( $level ) {
				return isset( $entry['level'] ) && $entry['level'] === $level;
			} ) );
		}
		return array_slice( $logs, 0, max( 1, (int) $limit ) );
	}

	/**
	 * Limpa todos os logs.
	 */
	public static function clear() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Auto-prune (chamado pelo cron).
	 */
	public static function prune() {
		$logs = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			return;
		}
		$cutoff = time() - ( 7 * DAY_IN_SECONDS );
		$logs   = array_values( array_filter( $logs, function( $entry ) use ( $cutoff ) {
			return isset( $entry['ts'] ) && (int) $entry['ts'] >= $cutoff;
		} ) );
		update_option( self::OPTION_KEY, $logs, false );
	}
}
