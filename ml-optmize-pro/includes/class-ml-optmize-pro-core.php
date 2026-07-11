<?php
/**
 * Core — orquestrador singleton do plugin.
 *
 * Conecta todos os modulos, expõe API interna, registra hooks centrais e
 * garante que tudo seja carregado na ordem correta.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Core {

	/**
	 * Singleton.
	 *
	 * @var ML_Optimize_Pro_Core|null
	 */
	private static $instance = null;

	/**
	 * Modulos carregados.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Singleton accessor.
	 *
	 * @return ML_Optimize_Pro_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor: inicializa todos os modulos.
	 */
	private function __construct() {
		// I18n primeiro.
		ML_Optimize_Pro_I18n::load();

		// Carrega modulos e chama register() em cada um se existir.
		$module_classes = array(
			'cache'             => 'ML_Optimize_Pro_Cache',
			'cache_preload'     => 'ML_Optimize_Pro_Cache_Preload',
			'browser_cache'     => 'ML_Optimize_Pro_Browser_Cache',
			'assets'            => 'ML_Optimize_Pro_Assets',
			'defer_js'          => 'ML_Optimize_Pro_Defer_JS',
			'delay_js'          => 'ML_Optimize_Pro_Delay_JS',
			'remove_unused_css' => 'ML_Optimize_Pro_Remove_Unused_CSS',
			'lazy_load'         => 'ML_Optimize_Pro_Lazy_Load',
			'lazy_render'       => 'ML_Optimize_Pro_Lazy_Render',
			'script_manager'    => 'ML_Optimize_Pro_Script_Manager',
			'bloat_remover'     => 'ML_Optimize_Pro_Bloat_Remover',
			'fonts'             => 'ML_Optimize_Pro_Fonts',
			'preload'           => 'ML_Optimize_Pro_Preload',
			'database'          => 'ML_Optimize_Pro_Database',
			'heartbeat'         => 'ML_Optimize_Pro_Heartbeat',
			'images'            => 'ML_Optimize_Pro_Images',
			'cdn'               => 'ML_Optimize_Pro_CDN',
			'speculation'       => 'ML_Optimize_Pro_Speculation_Rules',
			'hub'               => 'ML_Optimize_Pro_Performance_Hub',
			'rest'              => 'ML_Optimize_Pro_REST_API',
			'admin'             => 'ML_Optimize_Pro_Admin',
			'updater'           => 'ML_Optimize_Pro_Updater',
		);

		foreach ( $module_classes as $key => $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'register' ) ) {
				$class::register();
				$this->modules[ $key ] = $class;
			}
		}

		// Cron handlers.
		add_action( 'ml_optimize_pro_preload_event', array( 'ML_Optimize_Pro_Cache_Preload', 'run_cron' ) );
		add_action( 'ml_optimize_pro_db_cleanup_event', array( 'ML_Optimize_Pro_Database', 'run_cleanup' ) );
		add_action( 'ml_optimize_pro_logs_prune_event', array( 'ML_Optimize_Pro_Logs', 'prune' ) );
	}

	/**
	 * Impede clonagem.
	 */
	private function __clone() {}

	/**
	 * Retorna lista de modulos ativos.
	 *
	 * @return array
	 */
	public function get_modules() {
		return $this->modules;
	}
}
