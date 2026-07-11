<?php
/**
 * Uninstall — limpa todas as opcoes, transients, cache, MU e logs do ML Optimize Pro.
 *
 * Executado quando o plugin e desinstalado (nao apenas desativado). Mantem a instalacao
 * do WP limpa: remove o MU file, o diretorio de cache, as opcoes e a tabela de logs.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Remove opcoes principais e sub-opcoes conhecidas.
$options = array(
	'ml_optimize_pro_settings',
	'ml_optimize_pro_script_rules',
	'ml_optimize_pro_bloat_rules',
	'ml_optimize_pro_lazy_render_selectors',
	'ml_optimize_pro_excluded_pages',
	'ml_optimize_pro_cdn_excludes',
	'ml_optimize_pro_db_schedule',
	'ml_optimize_pro_hub_score',
	'ml_optimize_pro_logs',
	'ml_optimize_pro_last_cleanup',
	'ml_optimize_pro_last_preload',
	'ml_optimize_pro_cron_lock',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// 2. Limpa transients com prefixo do plugin.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\\_transient\\_ml\\_optimize\\_pro\\_%'
	    OR option_name LIKE '\\_transient\\_timeout\\_ml\\_optimize\\_pro\\_%'
	    OR option_name LIKE '\\_transient\\_ml\\_optpro\\_%'
	    OR option_name LIKE '\\_transient\\_timeout\\_ml\\_optpro\\_%'"
);

// 3. Limpa site transients (multisite).
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\\_site\\_transient\\_ml\\_optimize\\_pro\\_%'
	    OR option_name LIKE '\\_site\\_transient\\_timeout\\_ml\\_optimize\\_pro\\_%'"
);

// 4. Limpa MU file (must-use plugin loader).
$mu_file = WP_CONTENT_DIR . '/mu-plugins/ml-optmize-pro-mu.php';
if ( file_exists( $mu_file ) ) {
	// Best-effort cleanup; falha silenciosa e OK.
	@unlink( $mu_file );
}

// 5. Limpa diretorio de cache.
$cache_dir = WP_CONTENT_DIR . '/cache/ml-optmize-pro';
if ( is_dir( $cache_dir ) ) {
	ml_optimize_pro_uninstall_rmdir_recursive( $cache_dir );
}

// 6. Limpa schedules.
wp_clear_scheduled_hook( 'ml_optimize_pro_preload_event' );
wp_clear_scheduled_hook( 'ml_optimize_pro_db_cleanup_event' );
wp_clear_scheduled_hook( 'ml_optimize_pro_logs_prune_event' );

/**
 * Remove recursivamente um diretorio.
 *
 * @param string $dir Diretorio.
 * @return bool
 */
function ml_optimize_pro_uninstall_rmdir_recursive( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}
	$items = @scandir( $dir );
	if ( ! is_array( $items ) ) {
		return false;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if ( is_dir( $path ) ) {
			ml_optimize_pro_uninstall_rmdir_recursive( $path );
		} else {
			@unlink( $path );
		}
	}
	return @rmdir( $dir );
}
