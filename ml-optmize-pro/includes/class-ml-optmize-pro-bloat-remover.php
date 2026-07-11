<?php
/**
 * Bloat Remover — desabilita features do WP que nao sao usadas e custam performance.
 *
 * Cobre: emojis, embeds, dashicons frontend, jQuery Migrate, XML-RPC, query strings,
 * versao do WP, RSD, shortlink, RSS, REST API, block library, Gutenberg frontend,
 * WooCommerce bloat, Google Maps, Contact Form 7, Gravity Forms.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Bloat_Remover {

	/**
	 * Registra hooks.
	 */
	public static function register() {
		// Emojis.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_emojis' ) ) {
			add_action( 'init', array( __CLASS__, 'disable_emojis' ) );
		}
		// Embeds.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_embeds' ) ) {
			add_action( 'init', array( __CLASS__, 'disable_embeds' ) );
		}
		// Dashicons frontend.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_dashicons' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_dashicons' ) );
		}
		// jQuery Migrate.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_jquery_migrate' ) ) {
			add_action( 'wp_default_scripts', array( __CLASS__, 'remove_jquery_migrate' ) );
		}
		// XML-RPC.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( __CLASS__, 'remove_x_pingback' ) );
		}
		// Query strings.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_query_strings' ) ) {
			add_filter( 'script_loader_src', array( __CLASS__, 'remove_query_strings' ), 15 );
			add_filter( 'style_loader_src', array( __CLASS__, 'remove_query_strings' ), 15 );
		}
		// WP version.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_wp_version' ) ) {
			add_filter( 'the_generator', '__return_empty_string' );
			remove_action( 'wp_head', 'wp_generator' );
		}
		// RSD.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_rsd_link' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}
		// Shortlink.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_shortlink' ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		}
		// RSS.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_rss_feeds' ) ) {
			add_action( 'do_feed', array( __CLASS__, 'disable_feeds' ), 1 );
			add_action( 'do_feed_rdf', array( __CLASS__, 'disable_feeds' ), 1 );
			add_action( 'do_feed_rss', array( __CLASS__, 'disable_feeds' ), 1 );
			add_action( 'do_feed_rss2', array( __CLASS__, 'disable_feeds' ), 1 );
			add_action( 'do_feed_atom', array( __CLASS__, 'disable_feeds' ), 1 );
		}
		// REST API.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_rest_api_users' ) ) {
			add_filter( 'rest_endpoints', array( __CLASS__, 'disable_rest_users' ), 1000 );
		}
		// Block library.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_block_library' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_block_library' ), 100 );
		}
		// Gutenberg frontend.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_gutenberg_frontend' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_gutenberg_frontend' ), 100 );
		}
		// WooCommerce bloat.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_woocommerce_bloat' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_woocommerce_bloat' ), 99 );
		}
		// Google Maps.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_google_maps' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_google_maps' ), 100 );
		}
		// CF7.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_cf7' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_cf7' ), 100 );
		}
		// Gravity Forms.
		if ( ML_Optimize_Pro_Settings::is_on( 'bloat_gravityforms' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_gravityforms' ), 100 );
		}
	}

	/** Emojis. */
	public static function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', array( __CLASS__, 'disable_emojis_tinymce' ) );
		add_filter( 'wp_resource_hints', array( __CLASS__, 'disable_emojis_dns_prefetch' ), 10, 2 );
	}

	public static function disable_emojis_tinymce( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		}
		return array();
	}

	public static function disable_emojis_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/' );
			$urls          = array_diff( $urls, array( $emoji_svg_url ) );
		}
		return $urls;
	}

	/** Embeds. */
	public static function disable_embeds() {
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		add_filter( 'embed_oembed_discover', '__return_false' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_filter( 'rewrite_rules_array', array( __CLASS__, 'disable_embeds_rewrites' ) );
		remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );
	}

	public static function disable_embeds_rewrites( $rules ) {
		foreach ( $rules as $rule => $rewrite ) {
			if ( false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $rule ] );
			}
		}
		return $rules;
	}

	/** Dashicons. */
	public static function disable_dashicons() {
		if ( ! is_user_logged_in() ) {
			wp_deregister_style( 'dashicons' );
		}
	}

	/** jQuery Migrate. */
	public static function remove_jquery_migrate( $scripts ) {
		if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
			$script = $scripts->registered['jquery'];
			if ( $script->deps ) {
				$script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
			}
		}
	}

	/** X-Pingback. */
	public static function remove_x_pingback( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	/** Query strings. */
	public static function remove_query_strings( $src ) {
		if ( is_string( $src ) && false !== strpos( $src, '?ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/** RSS Feeds. */
	public static function disable_feeds() {
		wp_die( __( 'Feeds desabilitados. Visite o site para ver o conteudo.', 'ml-optmize-pro' ), '', array( 'response' => 403 ) );
	}

	/** REST users. */
	public static function disable_rest_users( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			if ( isset( $endpoints['/wp/v2/users'] ) ) {
				unset( $endpoints['/wp/v2/users'] );
			}
			if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
				unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
			}
		}
		return $endpoints;
	}

	/** Block library. */
	public static function disable_block_library() {
		if ( is_singular() && has_blocks() && is_main_query() ) {
			return;
		}
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'wc-block-style' );
	}

	/** Gutenberg frontend. */
	public static function disable_gutenberg_frontend() {
		// Remove o global-styles inline que Gutenberg injeta.
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
		remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	}

	/** WooCommerce bloat. */
	public static function disable_woocommerce_bloat() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		// Desabilita cart fragments global.
		wp_dequeue_script( 'wc-cart-fragments' );
		// Remove estilos de widgets em paginas nao-woocommerce.
		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			wp_dequeue_style( 'woocommerce-general' );
			wp_dequeue_style( 'woocommerce-layout' );
			wp_dequeue_style( 'woocommerce-smallscreen' );
			wp_dequeue_style( 'woocommerce_prettyPhoto_css' );
		}
	}

	/** Google Maps. */
	public static function disable_google_maps() {
		// Desregistra handles comuns.
		$handles = array( 'google-maps', 'gmaps', 'wp-google-maps', 'googlemap', 'google-maps-api', 'acf-google-map' );
		foreach ( $handles as $handle ) {
			wp_dequeue_script( $handle );
			wp_dequeue_style( $handle );
		}
	}

	/** Contact Form 7. */
	public static function disable_cf7() {
		if ( ! function_exists( 'wpcf7_contact_form' ) ) {
			return;
		}
		// Verifica se a pagina tem form CF7.
		if ( is_singular() ) {
			global $post;
			if ( $post && has_shortcode( (string) $post->post_content, 'contact-form-7' ) ) {
				return;
			}
		}
		wp_dequeue_script( 'contact-form-7' );
		wp_dequeue_style( 'contact-form-7' );
	}

	/** Gravity Forms. */
	public static function disable_gravityforms() {
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}
		if ( is_singular() ) {
			global $post;
			if ( $post && has_shortcode( (string) $post->post_content, 'gravityform' ) ) {
				return;
			}
		}
		wp_dequeue_script( 'gforms_gravityforms' );
		wp_dequeue_script( 'gforms_placeholder' );
		wp_dequeue_style( 'gforms_formsmain_css' );
		wp_dequeue_style( 'gforms_ready_class_css' );
		wp_dequeue_style( 'gforms_browsers_css' );
	}
}
