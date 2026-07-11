<?php
/**
 * Plugin Name:       ML Optimize Pro
 * Plugin URI:        https://mlopesdesign.com.br/plugins/ml-optmize-pro
 * Description:       Suite premium de otimizacao WordPress: cache de pagina, minify CSS/JS, defer/delay JavaScript, remove unused CSS, lazy load, lazy render, self-host Google Fonts, preload de recursos, Script Manager estilo Perfmatters, Bloat Remover, limpeza de banco, controle de heartbeat, CDN, Speculation Rules e Performance Hub com score CWV.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      6.8
 * Author:            ML Lopes Design
 * Author URI:        https://mlopesdesign.com.br
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ml-optmize-pro
 * Domain Path:       /languages
 * Network:           false
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ML_OPTIMIZE_PRO_VERSION',  '1.0.3' );
define( 'ML_OPTIMIZE_PRO_FILE',     __FILE__ );
define( 'ML_OPTIMIZE_PRO_PATH',     plugin_dir_path( __FILE__ ) );
define( 'ML_OPTIMIZE_PRO_URL',      plugin_dir_url( __FILE__ ) );
define( 'ML_OPTIMIZE_PRO_BASENAME', plugin_basename( __FILE__ ) );
define( 'ML_OPTIMIZE_PRO_SLUG',     'ml-optmize-pro' );
define( 'ML_OPTIMIZE_PRO_OPTION',   'ml_optimize_pro_settings' );
define( 'ML_OPTIMIZE_PRO_CACHE_DIR', WP_CONTENT_DIR . '/cache/ml-optmize-pro/' );
define( 'ML_OPTIMIZE_PRO_CACHE_URL', WP_CONTENT_URL . '/cache/ml-optmize-pro/' );
define( 'ML_OPTIMIZE_PRO_MU_FILE',  WP_CONTENT_DIR . '/mu-plugins/ml-optmize-pro-mu.php' );

// GitHub updater config (pode ser ajustado nas settings).
if ( ! defined( 'ML_OPTIMIZE_PRO_GITHUB_USER' ) ) {
	define( 'ML_OPTIMIZE_PRO_GITHUB_USER', 'mlopesdesign' );
}
if ( ! defined( 'ML_OPTIMIZE_PRO_GITHUB_REPO' ) ) {
	define( 'ML_OPTIMIZE_PRO_GITHUB_REPO', 'ml-optmize-pro' );
}

$ml_optimize_pro_includes = array(
	'includes/class-ml-optmize-pro-i18n.php',
	'includes/class-ml-optmize-pro-settings.php',
	'includes/class-ml-optmize-pro-activator.php',
	'includes/class-ml-optmize-pro-deactivator.php',
	'includes/class-ml-optmize-pro-logs.php',
	'includes/class-ml-optmize-pro-cache.php',
	'includes/class-ml-optmize-pro-cache-preload.php',
	'includes/class-ml-optmize-pro-browser-cache.php',
	'includes/class-ml-optmize-pro-assets.php',
	'includes/class-ml-optmize-pro-defer-js.php',
	'includes/class-ml-optmize-pro-delay-js.php',
	'includes/class-ml-optmize-pro-remove-unused-css.php',
	'includes/class-ml-optmize-pro-lazy-load.php',
	'includes/class-ml-optmize-pro-lazy-render.php',
	'includes/class-ml-optmize-pro-script-manager.php',
	'includes/class-ml-optmize-pro-bloat-remover.php',
	'includes/class-ml-optmize-pro-fonts.php',
	'includes/class-ml-optmize-pro-preload.php',
	'includes/class-ml-optmize-pro-database.php',
	'includes/class-ml-optmize-pro-heartbeat.php',
	'includes/class-ml-optmize-pro-images.php',
	'includes/class-ml-optmize-pro-cdn.php',
	'includes/class-ml-optmize-pro-speculation-rules.php',
	'includes/class-ml-optmize-pro-performance-hub.php',
	'includes/class-ml-optmize-pro-rest-api.php',
	'includes/class-ml-optmize-pro-admin.php',
	'includes/class-ml-optmize-pro-admin-page.php',
	'includes/class-ml-optmize-pro-updater.php',
	'includes/class-ml-optmize-pro-core.php',
);

foreach ( $ml_optimize_pro_includes as $ml_optimize_pro_file ) {
	$ml_optimize_pro_path = ML_OPTIMIZE_PRO_PATH . $ml_optimize_pro_file;
	if ( file_exists( $ml_optimize_pro_path ) ) {
		require_once $ml_optimize_pro_path;
	}
}

register_activation_hook( __FILE__, array( 'ML_Optimize_Pro_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ML_Optimize_Pro_Deactivator', 'deactivate' ) );

/**
 * Singleton accessor for the main plugin.
 *
 * @return ML_Optimize_Pro_Core
 */
function ml_optimize_pro() {
	return ML_Optimize_Pro_Core::get_instance();
}

ml_optimize_pro();
