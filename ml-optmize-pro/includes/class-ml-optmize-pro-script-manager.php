<?php
/**
 * Script Manager — desabilita scripts/CSS por URL / post type / post ID / page template.
 *
 * Implementa o padrao Perfmatters: regras salvas em option serializada, aplicadas
 * via filter script_loader_src e style_loader_src. Suporta regex, excecoes, modo
 * teste (so logged-in admins veem o efeito) e MU mode (must-use plugin).
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Script_Manager {

	const RULES_OPTION = 'ml_optimize_pro_script_rules';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'script_manager_enabled' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_unload' ), 9999 );
		add_action( 'wp_print_scripts', array( __CLASS__, 'maybe_remove_inline' ), 9999 );
		add_action( 'wp_print_styles', array( __CLASS__, 'maybe_remove_inline' ), 9999 );
	}

	/**
	 * Test mode — se ativo, so logged-in admins veem o efeito.
	 *
	 * @return bool
	 */
	public static function is_test_mode() {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'script_manager_test_mode' ) ) {
			return false;
		}
		return current_user_can( 'manage_options' ) && is_user_logged_in();
	}

	/**
	 * Aplica o unload: pega rules, filtra scripts/styles.
	 */
	public static function maybe_unload() {
		// Se test mode, so logged-in admin.
		$test_mode = ML_Optimize_Pro_Settings::is_on( 'script_manager_test_mode' );
		if ( $test_mode ) {
			if ( ! ( current_user_can( 'manage_options' ) && is_user_logged_in() ) ) {
				return;
			}
		}
		$rules = self::get_rules();
		if ( empty( $rules ) ) {
			return;
		}
		global $wp_scripts, $wp_styles;
		if ( ! is_object( $wp_scripts ) || ! is_object( $wp_styles ) ) {
			return;
		}
		$matched = self::match_url_rules( $rules );
		if ( empty( $matched ) ) {
			return;
		}
		foreach ( $matched as $rule ) {
			$handles = (array) ( isset( $rule['handles'] ) ? $rule['handles'] : array() );
			$type    = isset( $rule['type'] ) ? $rule['type'] : 'all'; // all | script | style
			$device  = isset( $rule['device'] ) ? $rule['device'] : 'all'; // all | desktop | mobile
			if ( 'mobile' === $device && ! wp_is_mobile() ) {
				continue;
			}
			if ( 'desktop' === $device && wp_is_mobile() ) {
				continue;
			}
			foreach ( $handles as $handle ) {
				$handle = trim( (string) $handle );
				if ( '' === $handle ) {
					continue;
				}
				$is_regex = 0 === strpos( $handle, '/' );
				if ( 'all' === $type || 'script' === $type ) {
					if ( $is_regex ) {
						self::unload_scripts_by_regex( $handle );
					} else {
						wp_deregister_script( $handle );
						wp_dequeue_script( $handle );
					}
				}
				if ( 'all' === $type || 'style' === $type ) {
					if ( $is_regex ) {
						self::unload_styles_by_regex( $handle );
					} else {
						wp_deregister_style( $handle );
						wp_dequeue_style( $handle );
					}
				}
			}
		}
	}

	/**
	 * Remove blocos inline (wp_print_scripts) para handles com regra.
	 */
	public static function maybe_remove_inline() {
		$rules = self::get_rules();
		if ( empty( $rules ) ) {
			return;
		}
		$matched = self::match_url_rules( $rules );
		if ( empty( $matched ) ) {
			return;
		}
		foreach ( $matched as $rule ) {
			$handles = (array) ( isset( $rule['handles'] ) ? $rule['handles'] : array() );
			foreach ( $handles as $handle ) {
				$handle = trim( (string) $handle );
				if ( '' === $handle ) {
					continue;
				}
				if ( 0 === strpos( $handle, '/' ) ) {
					continue;
				}
				// Remove inline extra data.
				wp_add_inline_script( $handle, '', 'after' );
			}
		}
	}

	/**
	 * Desenfileira scripts por regex no source.
	 *
	 * @param string $pattern Regex.
	 */
	private static function unload_scripts_by_regex( $pattern ) {
		global $wp_scripts;
		if ( ! is_object( $wp_scripts ) || empty( $wp_scripts->registered ) ) {
			return;
		}
		foreach ( $wp_scripts->registered as $handle => $dep ) {
			if ( isset( $dep->src ) && @preg_match( $pattern, $dep->src ) ) {
				wp_deregister_script( $handle );
				wp_dequeue_script( $handle );
			}
		}
	}

	/**
	 * Desenfileira styles por regex.
	 *
	 * @param string $pattern Regex.
	 */
	private static function unload_styles_by_regex( $pattern ) {
		global $wp_styles;
		if ( ! is_object( $wp_styles ) || empty( $wp_styles->registered ) ) {
			return;
		}
		foreach ( $wp_styles->registered as $handle => $dep ) {
			if ( isset( $dep->src ) && @preg_match( $pattern, $dep->src ) ) {
				wp_deregister_style( $handle );
				wp_dequeue_style( $handle );
			}
		}
	}

	/**
	 * Filtra rules que casam com a URL atual.
	 *
	 * @param array $rules Rules.
	 * @return array
	 */
	private static function match_url_rules( $rules ) {
		$matched = array();
		$uri     = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$post_id = is_singular() ? (int) get_queried_object_id() : 0;
		$pt      = is_singular() ? get_post_type() : '';
		foreach ( $rules as $rule ) {
			if ( self::rule_matches( $rule, $uri, $post_id, $pt ) ) {
				$matched[] = $rule;
			}
		}
		return $matched;
	}

	/**
	 * Testa se uma rule casa com a request.
	 *
	 * @param array  $rule    Rule.
	 * @param string $uri     URI.
	 * @param int    $post_id Post ID.
	 * @param string $pt      Post type.
	 * @return bool
	 */
	private static function rule_matches( $rule, $uri, $post_id, $pt ) {
		$scope = isset( $rule['scope'] ) ? $rule['scope'] : 'everywhere';
		switch ( $scope ) {
			case 'everywhere':
				return true;
			case 'current':
				if ( ! $post_id ) {
					return false;
				}
				$ids = isset( $rule['ids'] ) ? (array) $rule['ids'] : array();
				return in_array( $post_id, array_map( 'absint', $ids ), true );
			case 'post_type':
				$pts = isset( $rule['post_types'] ) ? (array) $rule['post_types'] : array();
				return $pt && in_array( $pt, $pts, true );
			case 'archive':
				return is_archive();
			case 'front_page':
				return is_front_page();
			case 'blog':
				return is_home();
			case 'search':
				return is_search();
			case '404':
				return is_404();
		}
		return false;
	}

	/**
	 * Retorna rules.
	 *
	 * @return array
	 */
	public static function get_rules() {
		$rules = get_option( self::RULES_OPTION, array() );
		if ( ! is_array( $rules ) ) {
			return array();
		}
		return $rules;
	}

	/**
	 * Salva rules.
	 *
	 * @param array $rules Rules.
	 * @return bool
	 */
	public static function save_rules( array $rules ) {
		// Filtra entradas validas.
		$clean = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$handles = isset( $rule['handles'] ) ? (array) $rule['handles'] : array();
			$handles = array_values( array_filter( array_map( function( $h ) {
				return is_string( $h ) ? sanitize_text_field( $h ) : '';
			}, $handles ) ) );
			if ( empty( $handles ) ) {
				continue;
			}
			$clean[] = array(
				'scope'      => isset( $rule['scope'] ) ? sanitize_key( $rule['scope'] ) : 'everywhere',
				'ids'        => isset( $rule['ids'] ) ? array_map( 'absint', (array) $rule['ids'] ) : array(),
				'post_types' => isset( $rule['post_types'] ) ? array_map( 'sanitize_key', (array) $rule['post_types'] ) : array(),
				'handles'    => $handles,
				'type'       => isset( $rule['type'] ) ? sanitize_key( $rule['type'] ) : 'all',
				'device'     => isset( $rule['device'] ) ? sanitize_key( $rule['device'] ) : 'all',
				'note'       => isset( $rule['note'] ) ? sanitize_text_field( $rule['note'] ) : '',
			);
		}
		return update_option( self::RULES_OPTION, $clean, false );
	}

	/**
	 * Lista todos os scripts enfileirados no momento (para UI).
	 *
	 * @return array
	 */
	public static function get_currently_enqueued() {
		$out = array( 'scripts' => array(), 'styles' => array() );
		global $wp_scripts, $wp_styles;
		if ( is_object( $wp_scripts ) && ! empty( $wp_scripts->registered ) ) {
			foreach ( $wp_scripts->registered as $handle => $dep ) {
				$out['scripts'][] = array(
					'handle' => $handle,
					'src'    => isset( $dep->src ) ? $dep->src : '',
				);
			}
		}
		if ( is_object( $wp_styles ) && ! empty( $wp_styles->registered ) ) {
			foreach ( $wp_styles->registered as $handle => $dep ) {
				$out['styles'][] = array(
					'handle' => $handle,
					'src'    => isset( $dep->src ) ? $dep->src : '',
				);
			}
		}
		return $out;
	}
}
