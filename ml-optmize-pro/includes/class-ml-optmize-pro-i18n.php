<?php
/**
 * i18n — bootstrap do text domain.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_I18n {

	/**
	 * Carrega o text domain.
	 */
	public static function load() {
		load_plugin_textdomain(
			'ml-optmize-pro',
			false,
			dirname( ML_OPTIMIZE_PRO_BASENAME ) . '/languages/'
		);
	}
}
