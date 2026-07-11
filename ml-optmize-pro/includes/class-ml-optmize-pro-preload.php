<?php
/**
 * Preload / Resource Hints — adiciona tags <link rel=preload|preconnect|dns-prefetch>
 * para recursos criticos e dominios externos.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Preload {

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		if ( ! ML_Optimize_Pro_Settings::is_on( 'preload_enabled' ) ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'output_preload' ), 1 );
		add_filter( 'wp_resource_hints', array( __CLASS__, 'add_resource_hints' ), 10, 2 );
	}

	/**
	 * Output das tags no <head>.
	 */
	public static function output_preload() {
		// Critical resources.
		$resources = (string) ML_Optimize_Pro_Settings::get( 'critical_resources', '' );
		if ( $resources ) {
			$list = array_filter( array_map( 'trim', preg_split( "/[\r\n]+/", $resources ) ) );
			foreach ( $list as $line ) {
				$parts = array_map( 'trim', explode( '|', $line ) );
				if ( count( $parts ) < 2 ) {
					continue;
				}
				$url   = $parts[0];
				$as    = $parts[1];
				$type  = isset( $parts[2] ) ? $parts[2] : '';
				$cross = in_array( strtolower( $as ), array( 'font', 'fetch' ), true ) ? ' crossorigin' : '';
				$type_attr = $type ? ' type="' . esc_attr( $type ) . '"' : '';
				echo '<link rel="preload" href="' . esc_url( $url ) . '" as="' . esc_attr( $as ) . '"' . $type_attr . $cross . ' />' . "\n";
			}
		}
	}

	/**
	 * Adiciona preconnect / dns-prefetch.
	 *
	 * @param array  $hints Hints atuais.
	 * @param string $relation_type Tipo.
	 * @return array
	 */
	public static function add_resource_hints( $hints, $relation_type ) {
		if ( 'preconnect' === $relation_type && ML_Optimize_Pro_Settings::is_on( 'preconnect_enabled' ) ) {
			$hosts = self::get_list( 'preconnect_hosts' );
			foreach ( $hosts as $host ) {
				$url = self::normalize_host( $host );
				if ( $url ) {
					$hints[] = array( 'href' => $url, 'crossorigin' => 'anonymous' );
				}
			}
		}
		if ( 'dns-prefetch' === $relation_type && ML_Optimize_Pro_Settings::is_on( 'dns_prefetch_enabled' ) ) {
			$hosts = self::get_list( 'dns_prefetch_hosts' );
			foreach ( $hosts as $host ) {
				$url = self::normalize_host( $host );
				if ( $url ) {
					$hints[] = '//' . $url;
				}
			}
		}
		return $hints;
	}

	/**
	 * Pega lista.
	 *
	 * @param string $key Chave.
	 * @return array
	 */
	private static function get_list( $key ) {
		$raw = (string) ML_Optimize_Pro_Settings::get( $key, '' );
		return array_filter( array_map( 'trim', preg_split( "/[\r\n]+/", $raw ) ) );
	}

	/**
	 * Normaliza host.
	 *
	 * @param string $host Host.
	 * @return string|null
	 */
	private static function normalize_host( $host ) {
		$host = trim( $host, " /\t\n\r\0\x0B" );
		$host = preg_replace( '#^https?://#i', '', $host );
		if ( '' === $host ) {
			return null;
		}
		return $host;
	}
}
