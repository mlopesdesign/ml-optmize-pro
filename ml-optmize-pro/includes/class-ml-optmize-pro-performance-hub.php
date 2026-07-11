<?php
/**
 * Performance Hub — dashboard de score estimado e contadores.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Performance_Hub {

	/**
	 * Calcula score estimado (0-100) baseado nas settings ativas.
	 *
	 * @return array
	 */
	public static function get_score() {
		$breakdown = self::score_breakdown();
		$total     = 0;
		$max       = 0;
		foreach ( $breakdown as $item ) {
			$total += $item['score'];
			$max   += $item['max'];
		}
		$percentage = $max > 0 ? round( ( $total / $max ) * 100 ) : 0;
		return array(
			'percentage' => $percentage,
			'total'      => $total,
			'max'        => $max,
			'breakdown'  => $breakdown,
		);
	}

	/**
	 * Itens que compoem o score.
	 *
	 * @return array
	 */
	public static function score_breakdown() {
		return array(
			array(
				'category' => 'Cache',
				'feature'  => 'Page cache ativado',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'cache_enabled' ) ? 10 : 0,
				'max'      => 10,
			),
			array(
				'category' => 'Cache',
				'feature'  => 'Cache preload',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'cache_preload' ) ? 5 : 0,
				'max'      => 5,
			),
			array(
				'category' => 'Cache',
				'feature'  => 'Browser cache',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'browser_cache_enabled' ) ? 5 : 0,
				'max'      => 5,
			),
			array(
				'category' => 'Files',
				'feature'  => 'Minify CSS',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'minify_css' ) ? 5 : 0,
				'max'      => 5,
			),
			array(
				'category' => 'Files',
				'feature'  => 'Minify JS',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'minify_js' ) ? 5 : 0,
				'max'      => 5,
			),
			array(
				'category' => 'Files',
				'feature'  => 'Minify HTML',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'minify_html' ) ? 3 : 0,
				'max'      => 3,
			),
			array(
				'category' => 'Files',
				'feature'  => 'Combine CSS',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'combine_css' ) ? 2 : 0,
				'max'      => 2,
			),
			array(
				'category' => 'Files',
				'feature'  => 'Combine JS',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'combine_js' ) ? 2 : 0,
				'max'      => 2,
			),
			array(
				'category' => 'Core Web Vitals',
				'feature'  => 'Remove Unused CSS',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'remove_unused_css' ) ? 8 : 0,
				'max'      => 8,
			),
			array(
				'category' => 'Core Web Vitals',
				'feature'  => 'Defer JS',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'defer_js' ) ? 7 : 0,
				'max'      => 7,
			),
			array(
				'category' => 'Core Web Vitals',
				'feature'  => 'Delay JS execution',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'delay_js' ) ? 5 : 0,
				'max'      => 5,
			),
			array(
				'category' => 'Images',
				'feature'  => 'Lazy load imagens',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'lazy_images' ) ? 5 : 0,
				'max'      => 5,
			),
			array(
				'category' => 'Images',
				'feature'  => 'Lazy load iframes',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'lazy_iframes' ) ? 3 : 0,
				'max'      => 3,
			),
			array(
				'category' => 'Images',
				'feature'  => 'Lazy YouTube',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'lazy_youtube' ) ? 2 : 0,
				'max'      => 2,
			),
			array(
				'category' => 'Images',
				'feature'  => 'Add missing dimensions',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'images_add_dimensions' ) ? 5 : 0,
				'max'      => 5,
			),
			array(
				'category' => 'Images',
				'feature'  => 'fetchpriority LCP',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'images_fetchpriority' ) ? 3 : 0,
				'max'      => 3,
			),
			array(
				'category' => 'Bloat',
				'feature'  => 'Disable emojis',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'bloat_emojis' ) ? 1 : 0,
				'max'      => 1,
			),
			array(
				'category' => 'Bloat',
				'feature'  => 'Disable embeds',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'bloat_embeds' ) ? 1 : 0,
				'max'      => 1,
			),
			array(
				'category' => 'Bloat',
				'feature'  => 'Disable dashicons (FE)',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'bloat_dashicons' ) ? 1 : 0,
				'max'      => 1,
			),
			array(
				'category' => 'Bloat',
				'feature'  => 'Remove jQuery Migrate',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'bloat_jquery_migrate' ) ? 1 : 0,
				'max'      => 1,
			),
			array(
				'category' => 'Bloat',
				'feature'  => 'Disable XML-RPC',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'bloat_xmlrpc' ) ? 1 : 0,
				'max'      => 1,
			),
			array(
				'category' => 'Bloat',
				'feature'  => 'Remove query strings',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'bloat_query_strings' ) ? 1 : 0,
				'max'      => 1,
			),
			array(
				'category' => 'Bloat',
				'feature'  => 'WooCommerce bloat',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'bloat_woocommerce_bloat' ) ? 1 : 0,
				'max'      => 1,
			),
			array(
				'category' => 'Fonts',
				'feature'  => 'Self-host Google Fonts',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'fonts_self_host' ) ? 5 : 0,
				'max'      => 5,
			),
			array(
				'category' => 'Fonts',
				'feature'  => 'Preload fonts',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'fonts_preload' ) ? 3 : 0,
				'max'      => 3,
			),
			array(
				'category' => 'Fonts',
				'feature'  => 'font-display swap',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'fonts_display_swap' ) ? 2 : 0,
				'max'      => 2,
			),
			array(
				'category' => 'Database',
				'feature'  => 'DB cleanup automatico',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'db_cleanup_enabled' ) ? 3 : 0,
				'max'      => 3,
			),
			array(
				'category' => 'Database',
				'feature'  => 'Limite de revisoes',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'db_cleanup_enabled' ) ? 2 : 0,
				'max'      => 2,
			),
			array(
				'category' => 'Resources',
				'feature'  => 'Speculation Rules',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'speculation_enabled' ) ? 2 : 0,
				'max'      => 2,
			),
			array(
				'category' => 'Resources',
				'feature'  => 'DNS prefetch',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'dns_prefetch_enabled' ) ? 1 : 0,
				'max'      => 1,
			),
			array(
				'category' => 'Resources',
				'feature'  => 'Preconnect',
				'score'    => ML_Optimize_Pro_Settings::is_on( 'preconnect_enabled' ) ? 1 : 0,
				'max'      => 1,
			),
		);
	}

	/**
	 * Resumo do banco.
	 *
	 * @return array
	 */
	public static function get_db_stats() {
		return ML_Optimize_Pro_Database::get_stats();
	}

	/**
	 * Resumo de modulos ativos.
	 *
	 * @return array
	 */
	public static function get_active_modules() {
		$modules = array(
			'cache'           => ML_Optimize_Pro_Settings::is_on( 'cache_enabled' ),
			'browser_cache'   => ML_Optimize_Pro_Settings::is_on( 'browser_cache_enabled' ),
			'minify'          => ML_Optimize_Pro_Settings::is_on( 'minify_css' ) || ML_Optimize_Pro_Settings::is_on( 'minify_js' ),
			'defer_js'        => ML_Optimize_Pro_Settings::is_on( 'defer_js' ),
			'delay_js'        => ML_Optimize_Pro_Settings::is_on( 'delay_js' ),
			'remove_unused'   => ML_Optimize_Pro_Settings::is_on( 'remove_unused_css' ),
			'lazy_load'       => ML_Optimize_Pro_Settings::is_on( 'lazy_images' ) || ML_Optimize_Pro_Settings::is_on( 'lazy_iframes' ),
			'script_manager'  => ML_Optimize_Pro_Settings::is_on( 'script_manager_enabled' ),
			'bloat'           => ML_Optimize_Pro_Settings::is_on( 'bloat_emojis' ) || ML_Optimize_Pro_Settings::is_on( 'bloat_embeds' ),
			'fonts'           => ML_Optimize_Pro_Settings::is_on( 'fonts_self_host' ),
			'db_cleanup'      => ML_Optimize_Pro_Settings::is_on( 'db_cleanup_enabled' ),
			'cdn'             => ML_Optimize_Pro_Settings::is_on( 'cdn_enabled' ),
			'speculation'     => ML_Optimize_Pro_Settings::is_on( 'speculation_enabled' ),
		);
		$active  = 0;
		$total   = count( $modules );
		foreach ( $modules as $on ) {
			if ( $on ) {
				$active++;
			}
		}
		return array(
			'modules'  => $modules,
			'active'   => $active,
			'total'    => $total,
		);
	}
}
