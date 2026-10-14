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
	 * Revisao do MU loader. Mudar quando o template mudar: sites existentes regravam sozinhos.
	 */
	const MU_REV = 2;

	/**
	 * Opcao que guarda a revisao do MU loader gravada neste site.
	 */
	const MU_REV_OPTION = 'ml_optimize_pro_mu_rev';

	/**
	 * Cria MU loader para o script manager.
	 */
	private static function create_mu_loader() {
		self::ensure_mu_loader( true );
	}

	/**
	 * Grava o MU loader (ativacao) ou regrava quando a revisao gravada e antiga (atualizacao).
	 *
	 * @param bool $force Grava mesmo que a revisao ja esteja em dia.
	 * @return bool
	 */
	public static function ensure_mu_loader( $force = false ) {
		// Em dia: revisao gravada confere E o arquivo tem o tamanho do template atual
		// (pega loader antigo regravado por um rollback para versao anterior).
		if ( ! $force && (int) get_option( self::MU_REV_OPTION, 0 ) === self::MU_REV
			&& @filesize( ML_OPTIMIZE_PRO_MU_FILE ) === strlen( self::mu_loader_template() ) ) {
			return true;
		}
		// Falha de escrita: nao tenta de novo a cada request.
		if ( ! $force && get_transient( 'ml_optimize_pro_mu_retry' ) ) {
			return false;
		}
		$mu_dir = WP_CONTENT_DIR . '/mu-plugins';
		if ( ! is_dir( $mu_dir ) ) {
			@wp_mkdir_p( $mu_dir );
		}
		$mu_file = $mu_dir . '/ml-optmize-pro-mu.php';
		// Sempre reescreve o MU pra garantir versao correta e curar MU files antigos
		// que tenham sido gravados com erro em instalacoes anteriores.
		$contents = self::mu_loader_template();
		$written  = self::write_mu_file( $mu_file, $contents );
		if ( false === $written ) {
			set_transient( 'ml_optimize_pro_mu_retry', 1, DAY_IN_SECONDS );
			self::log_error( 'create_mu_loader_write', new Exception( 'Nao foi possivel escrever ' . $mu_file ) );
			return false;
		}
		delete_transient( 'ml_optimize_pro_mu_retry' );
		update_option( self::MU_REV_OPTION, self::MU_REV, true );
		return true;
	}

	/**
	 * Grava o MU de forma atomica (arquivo temporario + rename): um request lendo o MU no meio
	 * da escrita nunca ve arquivo parcial. Nao escreve se o conteudo ja for identico.
	 *
	 * @param string $file     Caminho final.
	 * @param string $contents Conteudo.
	 * @return bool
	 */
	private static function write_mu_file( $file, $contents ) {
		if ( is_file( $file ) && @file_get_contents( $file ) === $contents ) {
			return true;
		}
		$tmp = $file . '.' . uniqid( 'tmp', true );
		if ( false === @file_put_contents( $tmp, $contents ) ) {
			return false;
		}
		if ( ! @rename( $tmp, $file ) ) {
			@unlink( $tmp );
			return (bool) @file_put_contents( $file, $contents );
		}
		return true;
	}

	/**
	 * Plugin ativo neste site (ou na rede)? Funciona tambem fora do wp-admin.
	 *
	 * @return bool
	 */
	public static function is_active_here() {
		if ( in_array( ML_OPTIMIZE_PRO_BASENAME, (array) get_option( 'active_plugins', array() ), true ) ) {
			return true;
		}
		if ( is_multisite() ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $network[ ML_OPTIMIZE_PRO_BASENAME ] );
		}
		return false;
	}

	/**
	 * Autorreparo do MU loader em qualquer request (painel, front, cron, WP-CLI).
	 *
	 * - Ativo: grava/regrava o loader quando a revisao gravada for antiga.
	 * - Inativo: so chegamos aqui se um loader ANTIGO (rev 1, sem condicao) carregou o plugin;
	 *   troca por um loader inerte (multisite) ou remove (site unico).
	 */
	public static function maybe_heal_mu_loader() {
		if ( self::is_active_here() ) {
			self::ensure_mu_loader();
			return;
		}
		if ( file_exists( ML_OPTIMIZE_PRO_MU_FILE ) && false === strpos( (string) @file_get_contents( ML_OPTIMIZE_PRO_MU_FILE ), 'Loader rev ' . self::MU_REV ) ) {
			self::release_mu_loader();
		}
	}

	/**
	 * Libera o loader quando o plugin deixa de estar ativo neste site.
	 * Multisite: o arquivo e unico para a rede, entao grava a versao inerte (rev 2 so carrega
	 * onde o plugin esta ativo) em vez de apagar. Site unico: remove o arquivo.
	 */
	public static function release_mu_loader() {
		delete_option( self::MU_REV_OPTION );
		if ( ! file_exists( ML_OPTIMIZE_PRO_MU_FILE ) ) {
			return;
		}
		if ( is_multisite() ) {
			self::write_mu_file( ML_OPTIMIZE_PRO_MU_FILE, self::mu_loader_template() );
			return;
		}
		@unlink( ML_OPTIMIZE_PRO_MU_FILE );
	}

	/**
	 * Conteudo do MU loader — delega para o plugin principal.
	 *
	 * Rev 2: so carrega o plugin quando ele esta ATIVO (antes carregava mesmo desativado) e
	 * quando o arquivo existe (antes dava fatal no site inteiro se a pasta do plugin sumisse).
	 * O caminho e montado com WP_PLUGIN_DIR em tempo de execucao (sobrevive a mudanca de servidor).
	 *
	 * @return string
	 */
	private static function mu_loader_template() {
		$basename_php = self::_php_string( ML_OPTIMIZE_PRO_BASENAME );
		$rev          = (int) self::MU_REV;
		return <<<PHP
<?php
/**
 * Must-Use loader for ML Optimize Pro.
 * Auto-generated. Do not edit. Loader rev {$rev}.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
\$ml_optimize_pro_mu_basename = {$basename_php};
\$ml_optimize_pro_mu_main     = WP_PLUGIN_DIR . '/' . \$ml_optimize_pro_mu_basename;
\$ml_optimize_pro_mu_on       = in_array( \$ml_optimize_pro_mu_basename, (array) get_option( 'active_plugins', array() ), true );
if ( ! \$ml_optimize_pro_mu_on && is_multisite() ) {
	\$ml_optimize_pro_mu_network = (array) get_site_option( 'active_sitewide_plugins', array() );
	\$ml_optimize_pro_mu_on      = isset( \$ml_optimize_pro_mu_network[ \$ml_optimize_pro_mu_basename ] );
}
// Carrega o plugin principal a partir deste MU file, garantindo que hooks
// muito cedo (plugins_loaded) ja tenham o plugin ativo. So quando ativo e presente.
if ( \$ml_optimize_pro_mu_on && is_file( \$ml_optimize_pro_mu_main ) ) {
	if ( ! defined( 'ML_OPTIMIZE_PRO_MU_ACTIVE' ) ) {
		define( 'ML_OPTIMIZE_PRO_MU_ACTIVE', true );
	}
	require_once \$ml_optimize_pro_mu_main;
}
unset( \$ml_optimize_pro_mu_basename, \$ml_optimize_pro_mu_main, \$ml_optimize_pro_mu_on, \$ml_optimize_pro_mu_network );

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
