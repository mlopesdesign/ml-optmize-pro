<?php
/**
 * Activator — instalacao: cria opcoes, diretorios, cron, MU file e log inicial.
 *
 * Tudo em try/catch para que uma falha nao quebre o painel (regra de ferro).
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Activator {

	/**
	 * Hook de ativacao.
	 */
	public static function activate() {
		try {
			self::create_options();
		} catch ( Exception $e ) {
			self::log_error( 'create_options', $e );
		}
		try {
			self::create_directories();
		} catch ( Exception $e ) {
			self::log_error( 'create_directories', $e );
		}
		try {
			self::schedule_crons();
		} catch ( Exception $e ) {
			self::log_error( 'schedule_crons', $e );
		}
		try {
			self::create_mu_loader();
		} catch ( Exception $e ) {
			self::log_error( 'create_mu_loader', $e );
		}
		try {
			self::first_activation_banner();
		} catch ( Exception $e ) {
			self::log_error( 'first_activation_banner', $e );
		}
	}

	/**
	 * Log de erro helper.
	 *
	 * @param string $step  Etapa.
	 * @param Exception $e Exception.
	 */
	private static function log_error( $step, $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ML Optimize Pro] Activation step [' . $step . '] error: ' . $e->getMessage() );
		}
	}

	/**
	 * Cria option de settings se nao existir.
	 */
	private static function create_options() {
		$existing = get_option( ML_Optimize_Pro_Settings::OPTION_KEY, null );
		if ( null === $existing || ! is_array( $existing ) ) {
			$defaults                  = ML_Optimize_Pro_Settings::defaults();
			$defaults['installed_at']  = current_time( 'mysql' );
			$defaults['settings_version'] = ML_OPTIMIZE_PRO_VERSION;
			update_option( ML_Optimize_Pro_Settings::OPTION_KEY, $defaults, false );
		} else {
			// Migra: garante chaves novas em upgrades.
			$defaults = ML_Optimize_Pro_Settings::defaults();
			$merged   = array_merge( $defaults, $existing );
			$merged['settings_version'] = ML_OPTIMIZE_PRO_VERSION;
			update_option( ML_Optimize_Pro_Settings::OPTION_KEY, $merged, false );
		}
	}

	/**
	 * Cria diretorio de cache.
	 */
	private static function create_directories() {
		$cache_dir = ML_OPTIMIZE_PRO_CACHE_DIR;
		if ( ! is_dir( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}
		// .htaccess protection.
		$htaccess = $cache_dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n" );
		}
		// index.php silence.
		$index = $cache_dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Agenda crons.
	 */
	private static function schedule_crons() {
		if ( ! wp_next_scheduled( 'ml_optimize_pro_preload_event' ) ) {
			wp_schedule_event( time() + 60, 'daily', 'ml_optimize_pro_preload_event' );
		}
		if ( ! wp_next_scheduled( 'ml_optimize_pro_db_cleanup_event' ) ) {
			wp_schedule_event( time() + 300, 'weekly', 'ml_optimize_pro_db_cleanup_event' );
		}
		if ( ! wp_next_scheduled( 'ml_optimize_pro_logs_prune_event' ) ) {
			wp_schedule_event( time() + 3600, 'daily', 'ml_optimize_pro_logs_prune_event' );
		}
	}

	/**
	 * Cria MU loader para o script manager.
	 */
	private static function create_mu_loader() {
		$mu_dir = WP_CONTENT_DIR . '/mu-plugins';
		if ( ! is_dir( $mu_dir ) ) {
			@wp_mkdir_p( $mu_dir );
		}
		$mu_file = $mu_dir . '/ml-optmize-pro-mu.php';
		// Sempre reescreve o MU pra garantir versao correta e curar MU files antigos
		// que tenham sido gravados com erro em instalacoes anteriores.
		$contents = self::mu_loader_template();
		$written  = @file_put_contents( $mu_file, $contents );
		if ( false === $written ) {
			self::log_error( 'create_mu_loader_write', new Exception( 'Nao foi possivel escrever ' . $mu_file ) );
		}
	}

	/**
	 * Conteudo do MU loader — delega para o plugin principal.
	 *
	 * @return string
	 */
	private static function mu_loader_template() {
		$plugin_file = ML_OPTIMIZE_PRO_FILE;
		// BUGFIX 1.0.1: trocar $this->_php_string por self::_php_string (era fatal
		// em PHP 8.x por uso de $this em metodo estatico).
		$plugin_file_php = self::_php_string( $plugin_file );
		return <<<PHP
<?php
/**
 * Must-Use loader for ML Optimize Pro.
 * Auto-generated. Do not edit.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'ML_OPTIMIZE_PRO_MU_ACTIVE' ) ) {
	define( 'ML_OPTIMIZE_PRO_MU_ACTIVE', true );
}
// Carrega o plugin principal a partir deste MU file, garantindo que hooks
// muito cedo (plugins_loaded) ja tenham o plugin ativo.
require_once {$plugin_file_php};
PHP;
	}

	/**
	 * Serializa string PHP com aspas e escapes para uso em heredoc.
	 *
	 * @param string $s String.
	 * @return string
	 */
	private static function _php_string( $s ) {
		return "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $s ) . "'";
	}

	/**
	 * Marca a primeira ativacao (para o banner de onboarding).
	 */
	private static function first_activation_banner() {
		$opts = get_option( ML_Optimize_Pro_Settings::OPTION_KEY, array() );
		if ( empty( $opts['first_activation_done'] ) ) {
			$opts['first_activation_done'] = 1;
			$opts['installed_at']         = current_time( 'mysql' );
			update_option( ML_Optimize_Pro_Settings::OPTION_KEY, $opts, false );
		}
	}
}
