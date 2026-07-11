<?php
/**
 * Database — limpeza e otimizacao do banco de dados.
 *
 * - Limpa: revisoes, autodrafts, trash, spam comments, transients expirados.
 * - Otimiza tabelas.
 * - Limita revisoes futuras.
 * - Cron: diario / semanal.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Database {

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'db_cleanup_enabled' ) ) {
			return;
		}
		add_filter( 'wp_revisions_to_keep', array( __CLASS__, 'limit_revisions' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_auto_disable_autosave' ) );
	}

	/**
	 * Limita revisoes.
	 *
	 * @param int $num Default.
	 * @return int
	 */
	public static function limit_revisions( $num ) {
		$limit = (int) ML_Optimize_Pro_Settings::get( 'db_revisions_limit', 5 );
		return max( 0, $limit );
	}

	/**
	 * Desabilita autosave se configurado.
	 */
	public static function maybe_auto_disable_autosave() {
		if ( ! defined( 'AUTOSAVE_INTERVAL' ) ) {
			return;
		}
		// 1 hora por padrao para reduzir carga.
		// Pode ser alterado em wp-config.php.
	}

	/**
	 * Hook do cron — roda cleanup.
	 */
	public static function run_cleanup() {
		$stats = self::run( 'scheduled' );
		ML_Optimize_Pro_Logs::add( 'database', sprintf( 'Cleanup automatico: %s', wp_json_encode( $stats ) ), 'success' );
		update_option( 'ml_optimize_pro_last_cleanup', current_time( 'mysql' ), false );
	}

	/**
	 * Executa cleanup manual via admin.
	 *
	 * @param string $source Fonte.
	 * @return array Estatisticas.
	 */
	public static function run( $source = 'manual' ) {
		global $wpdb;
		$stats = array(
			'revisions'    => 0,
			'autodrafts'   => 0,
			'trash'        => 0,
			'spam'         => 0,
			'transients'   => 0,
			'optimize'     => 0,
		);
		// 1) Revisoes.
		if ( ML_Optimize_Pro_Settings::is_on( 'db_cleanup_revisions' ) ) {
			$ids = $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT 5000"
			);
			if ( ! empty( $ids ) ) {
				$count = count( $ids );
				foreach ( array_chunk( $ids, 200 ) as $chunk ) {
					$ids_chunk = array_map( 'absint', $chunk );
					$placeholders = implode( ',', array_fill( 0, count( $ids_chunk ), '%d' ) );
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE ID IN ($placeholders)", $ids_chunk ) );
				}
				$stats['revisions'] = $count;
			}
		}
		// 2) Autodrafts.
		if ( ML_Optimize_Pro_Settings::is_on( 'db_cleanup_autodrafts' ) ) {
			$ids = $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' LIMIT 5000"
			);
			if ( ! empty( $ids ) ) {
				$count = count( $ids );
				foreach ( array_chunk( $ids, 200 ) as $chunk ) {
					$ids_chunk = array_map( 'absint', $chunk );
					$placeholders = implode( ',', array_fill( 0, count( $ids_chunk ), '%d' ) );
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE ID IN ($placeholders)", $ids_chunk ) );
				}
				$stats['autodrafts'] = $count;
			}
		}
		// 3) Trash.
		if ( ML_Optimize_Pro_Settings::is_on( 'db_cleanup_trash' ) ) {
			$count = (int) $wpdb->query(
				"DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'"
			);
			$stats['trash'] = $count;
		}
		// 4) Spam comments.
		if ( ML_Optimize_Pro_Settings::is_on( 'db_cleanup_spam' ) ) {
			$count = (int) $wpdb->query(
				"DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
			);
			$stats['spam'] = $count;
		}
		// 5) Transients expirados.
		if ( ML_Optimize_Pro_Settings::is_on( 'db_cleanup_transients' ) ) {
			$time = time();
			$count = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE a, b FROM {$wpdb->options} a
					 LEFT JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, '_transient_timeout_', '_transient_')
					 WHERE a.option_name LIKE '_transient_timeout_%' AND a.option_value < %d",
					$time
				)
			);
			$stats['transients'] = $count;
		}
		// 6) Optimize tables.
		if ( ML_Optimize_Pro_Settings::is_on( 'db_cleanup_optimize' ) && 'manual' === $source ) {
			$tables = $wpdb->get_results( "SHOW TABLES", ARRAY_N );
			$count  = 0;
			if ( is_array( $tables ) ) {
				foreach ( $tables as $row ) {
					$table = $row[0];
					if ( 0 === strpos( $table, $wpdb->prefix ) ) {
						$wpdb->query( "OPTIMIZE TABLE `{$table}`" );
						$count++;
					}
				}
			}
			$stats['optimize'] = $count;
		}
		return $stats;
	}

	/**
	 * Estatisticas atuais (para o dashboard).
	 *
	 * @return array
	 */
	public static function get_stats() {
		global $wpdb;
		$out = array();
		$out['revisions']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
		$out['autodrafts'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
		$out['trash']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
		$out['spam']       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
		$out['transients'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%'" );
		$out['orphan_meta'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
		$out['db_size']     = self::get_db_size();
		return $out;
	}

	/**
	 * Tamanho do banco.
	 *
	 * @return string
	 */
	public static function get_db_size() {
		global $wpdb;
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s",
				DB_NAME
			)
		);
		if ( ! $result ) {
			return '0 KB';
		}
		return self::format_bytes( (int) $result );
	}

	/**
	 * Formata bytes.
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	public static function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );
		$bytes /= ( 1 << ( 10 * $pow ) );
		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}
