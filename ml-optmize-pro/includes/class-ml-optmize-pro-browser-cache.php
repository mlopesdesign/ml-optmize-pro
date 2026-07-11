<?php
/**
 * Browser Cache — adiciona headers de cache (expires, cache-control) no .htaccess.
 *
 * Estrategia: em vez de gerar headers via PHP (que sobe TTFB), escreve no .htaccess
 * uma vez. Detecta nginx vs apache vs litespeed e usa o metodo certo.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Browser_Cache {

	const MARKER_START = '# BEGIN ML Optimize Pro Browser Cache';
	const MARKER_END   = '# END ML Optimize Pro Browser Cache';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_flush_rules' ) );
	}

	/**
	 * Aplica as regras no .htaccess.
	 *
	 * @return bool|WP_Error
	 */
	public static function apply() {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'browser_cache_enabled' ) ) {
			return self::remove();
		}
		$server = self::detect_server();
		if ( 'apache' === $server || 'litespeed' === $server ) {
			return self::write_htaccess();
		}
		// Nginx / outros: fornece snippet pro user aplicar manualmente.
		return true;
	}

	/**
	 * Remove as regras.
	 *
	 * @return bool|WP_Error
	 */
	public static function remove() {
		$htaccess = self::htaccess_path();
		if ( ! file_exists( $htaccess ) ) {
			return true;
		}
		$contents = file_get_contents( $htaccess );
		if ( false === $contents ) {
			return new WP_Error( 'ml_optpro_bc_read', __( 'Nao foi possivel ler .htaccess.', 'ml-optmize-pro' ) );
		}
		$clean = preg_replace( '/' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . "\s*/is", '', $contents );
		if ( null === $clean ) {
			return new WP_Error( 'ml_optpro_bc_regex', __( 'Falha no regex de limpeza.', 'ml-optmize-pro' ) );
		}
		if ( $clean !== $contents ) {
			// Faz backup.
			@copy( $htaccess, $htaccess . '.ml-optpro.bak' );
			if ( false === file_put_contents( $htaccess, $clean ) ) {
				return new WP_Error( 'ml_optpro_bc_write', __( 'Nao foi possivel escrever .htaccess.', 'ml-optmize-pro' ) );
			}
		}
		return true;
	}

	/**
	 * Escreve regras no .htaccess.
	 *
	 * @return bool|WP_Error
	 */
	private static function write_htaccess() {
		$htaccess = self::htaccess_path();
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, '' );
		}
		$contents = file_get_contents( $htaccess );
		if ( false === $contents ) {
			return new WP_Error( 'ml_optpro_bc_read', __( 'Nao foi possivel ler .htaccess.', 'ml-optmize-pro' ) );
		}
		// Remove versao antiga.
		$contents = preg_replace( '/' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . "\s*/is", '', $contents );
		$expiry  = (int) ML_Optimize_Pro_Settings::get( 'browser_cache_expiry', 365 * DAY_IN_SECONDS );
		$block   = self::MARKER_START . "\n" . self::apache_rules( $expiry ) . "\n" . self::MARKER_END . "\n";
		$new     = $block . $contents;
		@copy( $htaccess, $htaccess . '.ml-optpro.bak' );
		if ( false === file_put_contents( $htaccess, $new ) ) {
			return new WP_Error( 'ml_optpro_bc_write', __( 'Nao foi possivel escrever .htaccess. Verifique permissoes.', 'ml-optmize-pro' ) );
		}
		ML_Optimize_Pro_Logs::add( 'browser_cache', 'Regras de browser cache aplicadas no .htaccess.', 'success' );
		return true;
	}

	/**
	 * Gera bloco de regras Apache.
	 *
	 * @param int $expiry Expiry em segundos.
	 * @return string
	 */
	public static function apache_rules( $expiry ) {
		$expiry = max( 60, (int) $expiry );
		return <<<HTACCESS
<IfModule mod_expires.c>
ExpiresActive On
ExpiresDefault A0
ExpiresByType text/css "access plus {$expiry} seconds"
ExpiresByType application/javascript "access plus {$expiry} seconds"
ExpiresByType text/javascript "access plus {$expiry} seconds"
ExpiresByType application/x-javascript "access plus {$expiry} seconds"
ExpiresByType image/jpeg "access plus {$expiry} seconds"
ExpiresByType image/jpg "access plus {$expiry} seconds"
ExpiresByType image/png "access plus {$expiry} seconds"
ExpiresByType image/gif "access plus {$expiry} seconds"
ExpiresByType image/webp "access plus {$expiry} seconds"
ExpiresByType image/avif "access plus {$expiry} seconds"
ExpiresByType image/svg+xml "access plus {$expiry} seconds"
ExpiresByType image/x-icon "access plus {$expiry} seconds"
ExpiresByType font/woff "access plus {$expiry} seconds"
ExpiresByType font/woff2 "access plus {$expiry} seconds"
ExpiresByType application/font-woff "access plus {$expiry} seconds"
ExpiresByType application/font-woff2 "access plus {$expiry} seconds"
ExpiresByType video/mp4 "access plus {$expiry} seconds"
ExpiresByType application/pdf "access plus {$expiry} seconds"
</IfModule>
<IfModule mod_headers.c>
<FilesMatch "\.(css|js|jpg|jpeg|png|gif|webp|avif|svg|woff|woff2|ico|mp4|pdf)$">
Header set Cache-Control "public, max-age={$expiry}, immutable"
</FilesMatch>
</IfModule>
HTACCESS;
	}

	/**
	 * Gera snippet Nginx (para exibir nas instrucoes).
	 *
	 * @param int $expiry Expiry em segundos.
	 * @return string
	 */
	public static function nginx_rules( $expiry ) {
		$expiry = max( 60, (int) $expiry );
		return <<<NGINX
# ML Optimize Pro - Browser Cache
location ~* \.(css|js|jpg|jpeg|png|gif|webp|avif|svg|woff|woff2|ico|mp4|pdf)$ {
    expires {$expiry}s;
    add_header Cache-Control "public, max-age={$expiry}, immutable";
    access_log off;
    log_not_found off;
}
NGINX;
	}

	/**
	 * Detecta servidor web.
	 *
	 * @return string apache|nginx|litespeed|unknown
	 */
	public static function detect_server() {
		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$sw = strtolower( (string) $_SERVER['SERVER_SOFTWARE'] );
			if ( false !== strpos( $sw, 'apache' ) ) {
				return 'apache';
			}
			if ( false !== strpos( $sw, 'litespeed' ) ) {
				return 'litespeed';
			}
			if ( false !== strpos( $sw, 'nginx' ) ) {
				return 'nginx';
			}
		}
		// Fallback: checa existencia do .htaccess.
		if ( file_exists( self::htaccess_path() ) ) {
			return 'apache';
		}
		return 'unknown';
	}

	/**
	 * Caminho do .htaccess.
	 *
	 * @return string
	 */
	public static function htaccess_path() {
		return ABSPATH . '.htaccess';
	}

	/**
	 * Verifica se o flush de regras precisa ser disparado.
	 */
	public static function maybe_flush_rules() {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'browser_cache_enabled' ) ) {
			return;
		}
		$flag = get_option( 'ml_optimize_pro_bc_applied' );
		if ( ! $flag ) {
			self::apply();
			update_option( 'ml_optimize_pro_bc_applied', 1, false );
		}
	}
}
