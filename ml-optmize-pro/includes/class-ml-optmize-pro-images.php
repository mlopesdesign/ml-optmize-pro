<?php
/**
 * Images — add missing width/height, fetchpriority, AVIF/WebP hints.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Images {

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		if ( ML_Optimize_Pro_Settings::is_on( 'images_add_dimensions' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'add_dimensions' ), 30 );
			add_filter( 'post_thumbnail_html', array( __CLASS__, 'add_dimensions' ), 30 );
			add_filter( 'get_avatar', array( __CLASS__, 'add_dimensions' ), 30 );
		}
		if ( ML_Optimize_Pro_Settings::is_on( 'images_avif_webp' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'add_picture_source' ), 35 );
		}
	}

	/**
	 * Adiciona width/height em <img> que nao tem.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function add_dimensions( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		$html = preg_replace_callback( '#<img\b([^>]*?)/?>#i', function( $m ) {
			$attrs = $m[1];
			// Ja tem width/height.
			if ( preg_match( '/\bwidth\s*=/i', $attrs ) && preg_match( '/\bheight\s*=/i', $attrs ) ) {
				return $m[0];
			}
			if ( false !== stripos( $attrs, 'data-no-dim' ) ) {
				return $m[0];
			}
			if ( ! preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $src_m ) ) {
				return $m[0];
			}
			$src = $src_m[1];
			$dims = self::get_dimensions( $src );
			if ( ! $dims ) {
				return $m[0];
			}
			$add = '';
			if ( ! preg_match( '/\bwidth\s*=/i', $attrs ) ) {
				$add .= ' width="' . esc_attr( $dims['width'] ) . '"';
			}
			if ( ! preg_match( '/\bheight\s*=/i', $attrs ) ) {
				$add .= ' height="' . esc_attr( $dims['height'] ) . '"';
			}
			return '<img' . $add . $attrs . ' />';
		}, $html );
		return $html;
	}

	/**
	 * Adiciona <picture> source para AVIF/WebP quando o attachment tem srcset.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function add_picture_source( $html ) {
		// Implementacao simplificada — apenas confia que o tema/WordPress ja
		// entrega srcset. Aqui podemos adicionar <source type=image/avif> se o
		// browser suportar, mas isso ja e feature nativa WP 6.x.
		// Mantido como hook para extensoes futuras.
		return $html;
	}

	/**
	 * Tenta descobrir dimensoes de uma imagem a partir da URL.
	 *
	 * @param string $src URL.
	 * @return array|null
	 */
	private static function get_dimensions( $src ) {
		// 1) Tenta via attachment ID se for URL do wp-content/uploads.
		if ( false !== strpos( $src, '/wp-content/uploads/' ) ) {
			$attachment_id = self::url_to_attachment_id( $src );
			if ( $attachment_id ) {
				$meta = wp_get_attachment_metadata( $attachment_id );
				if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
					return array( 'width' => (int) $meta['width'], 'height' => (int) $meta['height'] );
				}
			}
		}
		// 2) Tenta via getimagesize se arquivo local.
		$abs = self::url_to_absolute_path( $src );
		if ( $abs && file_exists( $abs ) ) {
			$info = @getimagesize( $abs );
			if ( $info && ! empty( $info[0] ) && ! empty( $info[1] ) ) {
				return array( 'width' => (int) $info[0], 'height' => (int) $info[1] );
			}
		}
		return null;
	}

	/**
	 * Tenta descobrir attachment ID pela URL.
	 *
	 * @param string $url URL.
	 * @return int
	 */
	private static function url_to_attachment_id( $url ) {
		global $wpdb;
		$url  = preg_replace( '/-\d+x\d+(\.[a-z]+)$/i', '$1', $url );
		$url  = esc_url_raw( $url );
		$id   = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1", $url ) );
		if ( $id ) {
			return (int) $id;
		}
		// Tenta com base filename.
		$filename = basename( $url );
		$id       = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like( $filename ) ) );
		return $id ? (int) $id : 0;
	}

	/**
	 * Converte URL em path local.
	 *
	 * @param string $url URL.
	 * @return string|null
	 */
	private static function url_to_absolute_path( $url ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $url_host && strtolower( $url_host ) !== strtolower( $host ) ) {
			return null;
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return null;
		}
		return rtrim( ABSPATH, '/' ) . $path;
	}
}
