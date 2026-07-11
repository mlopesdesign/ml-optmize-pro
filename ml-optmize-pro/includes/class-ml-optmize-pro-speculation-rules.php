<?php
/**
 * Speculation Rules — usa a Speculation Rules API moderna (Chrome 109+)
 * para pre-renderizar links quando o usuario passa o mouse.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Speculation_Rules {

	const HANDLE = 'ml-optmize-pro-speculation';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		if ( ! ML_Optimize_Pro_Settings::is_on( 'speculation_enabled' ) ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'output_json' ), 1 );
	}

	/**
	 * Output da tag <script type="speculationrules"> com JSON.
	 */
	public static function output_json() {
		$eagerness = ML_Optimize_Pro_Settings::get( 'speculation_eagerness', 'moderate' );
		if ( ! in_array( $eagerness, array( 'immediate', 'moderate', 'conservative' ), true ) ) {
			$eagerness = 'moderate';
		}
		$rules = array(
			'prerender' => array(
				array(
					'source'    => 'document',
					'where'     => array( 'and' => array( array( 'href_matches' => '/*' ) ) ),
					'eagerness' => $eagerness,
				),
			),
		);
		echo '<script type="speculationrules">' . wp_json_encode( $rules ) . '</script>' . "\n";
	}
}
