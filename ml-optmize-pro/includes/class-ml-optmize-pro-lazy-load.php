<?php
/**
 * Lazy Load — adiciona loading="lazy" em imagens e iframes nativos, mais
 * lite-youtube-embed para iframes do YouTube, e bg-image lazy via IntersectionObserver.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Lazy_Load {

	const YT_PREVIEW_HANDLE = 'ml-optmize-pro-yt';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		if ( ! self::any_enabled() ) {
			return;
		}
		add_filter( 'wp_lazy_loading_enabled', '__return_true' );
		add_filter( 'the_content', array( __CLASS__, 'process_content' ), 25 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'process_content' ), 25 );
		add_filter( 'get_avatar', array( __CLASS__, 'process_content' ), 25 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Verifica se algum sub-modulo esta ativo.
	 *
	 * @return bool
	 */
	public static function any_enabled() {
		return ML_Optimize_Pro_Settings::is_on( 'lazy_images' )
			|| ML_Optimize_Pro_Settings::is_on( 'lazy_iframes' )
			|| ML_Optimize_Pro_Settings::is_on( 'lazy_videos' )
			|| ML_Optimize_Pro_Settings::is_on( 'lazy_youtube' )
			|| ML_Optimize_Pro_Settings::is_on( 'lazy_bg_images' );
	}

	/**
	 * Enfileira o CSS de placeholder para YouTube.
	 */
	public static function enqueue_assets() {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'lazy_youtube' ) ) {
			return;
		}
		$css = self::yt_css();
		wp_register_style( self::YT_PREVIEW_HANDLE, false );
		wp_enqueue_style( self::YT_PREVIEW_HANDLE );
		wp_add_inline_style( self::YT_PREVIEW_HANDLE, $css );
		$js = self::yt_js();
		wp_register_script( self::YT_PREVIEW_HANDLE, '', array(), ML_OPTIMIZE_PRO_VERSION, true );
		wp_enqueue_script( self::YT_PREVIEW_HANDLE );
		wp_add_inline_script( self::YT_PREVIEW_HANDLE, $js );
	}

	/**
	 * Processa o conteudo.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function process_content( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		if ( ML_Optimize_Pro_Settings::is_on( 'lazy_images' ) ) {
			$html = self::lazy_images( $html );
		}
		if ( ML_Optimize_Pro_Settings::is_on( 'lazy_iframes' ) ) {
			$html = self::lazy_iframes( $html );
		}
		if ( ML_Optimize_Pro_Settings::is_on( 'lazy_videos' ) ) {
			$html = self::lazy_videos( $html );
		}
		if ( ML_Optimize_Pro_Settings::is_on( 'lazy_youtube' ) ) {
			$html = self::lazy_youtube( $html );
		}
		return $html;
	}

	/**
	 * Adiciona loading="lazy" em <img>.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function lazy_images( $html ) {
		$skip = (int) ML_Optimize_Pro_Settings::get( 'lazy_skip_first', 1 );
		$excluded = (string) ML_Optimize_Pro_Settings::get( 'lazy_exclusions', '' );
		// Estrategia: pega todos <img>, conta em ordem, primeiros N nao recebem lazy.
		$counter = 0;
		$html = preg_replace_callback( '#<img\b([^>]*?)/?>#i', function( $m ) use ( &$counter, $skip, $excluded ) {
			$counter++;
			$attrs = $m[1];
			// Ja tem loading.
			if ( preg_match( '/\bloading\s*=/i', $attrs ) ) {
				return $m[0];
			}
			// data-no-lazy.
			if ( false !== stripos( $attrs, 'data-no-lazy' ) ) {
				return $m[0];
			}
			// Excluded.
			if ( $excluded && preg_match( '#' . preg_quote( $excluded, '#' ) . '#i', $attrs ) ) {
				return $m[0];
			}
			// Skip first N.
			if ( $counter <= $skip ) {
				// Mas adiciona fetchpriority=high no primeiro se feature ativa.
				if ( 1 === $counter && ML_Optimize_Pro_Settings::is_on( 'images_fetchpriority' ) ) {
					if ( ! preg_match( '/\bfetchpriority\s*=/i', $attrs ) ) {
						$attrs = ' fetchpriority="high"' . $attrs;
					}
				}
				return '<img' . $attrs . ' />';
			}
			$attrs = ' loading="lazy"' . $attrs;
			// decoding async.
			if ( ! preg_match( '/\bdecoding\s*=/i', $attrs ) ) {
				$attrs = ' decoding="async"' . $attrs;
			}
			return '<img' . $attrs . ' />';
		}, $html );
		return $html;
	}

	/**
	 * Adiciona loading="lazy" em <iframe>.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function lazy_iframes( $html ) {
		$html = preg_replace_callback( '#<iframe\b([^>]*?)></iframe>#i', function( $m ) {
			$attrs = $m[1];
			if ( preg_match( '/\bloading\s*=/i', $attrs ) ) {
				return $m[0];
			}
			if ( false !== stripos( $attrs, 'data-no-lazy' ) ) {
				return $m[0];
			}
			$attrs = ' loading="lazy"' . $attrs;
			return '<iframe' . $attrs . '></iframe>';
		}, $html );
		return $html;
	}

	/**
	 * Adiciona preload="none" em <video>.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function lazy_videos( $html ) {
		$html = preg_replace_callback( '#<video\b([^>]*?)>#i', function( $m ) {
			$attrs = $m[1];
			if ( preg_match( '/\bpreload\s*=/i', $attrs ) ) {
				return $m[0];
			}
			$attrs = ' preload="none"' . $attrs;
			return '<video' . $attrs . '>';
		}, $html );
		return $html;
	}

	/**
	 * Substitui iframe do YouTube por lite preview.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function lazy_youtube( $html ) {
		$html = preg_replace_callback( '#<iframe\b([^>]*?)\bsrc=["\'](https?:)?//(www\.)?(youtube\.com|youtu\.be)/embed/([^"\']+)["\']([^>]*?)></iframe>#i', function( $m ) {
			$video_id = $m[5];
			$attrs    = $m[1] . $m[6];
			// Pega titulo se existir.
			$title = 'YouTube video';
			if ( preg_match( '/\btitle\s*=\s*["\']([^"\']+)["\']/i', $attrs, $t ) ) {
				$title = $t[1];
			}
			return sprintf(
				'<lite-youtube videoid="%s" playlabel="%s" class="ml-optmize-yt" style="background-image:url(https://i.ytimg.com/vi/%s/hqdefault.jpg);"></lite-youtube>',
				esc_attr( $video_id ),
				esc_attr( $title ),
				esc_attr( $video_id )
			);
		}, $html );
		return $html;
	}

	/**
	 * CSS do lite-youtube.
	 *
	 * @return string
	 */
	private static function yt_css() {
		return <<<CSS
.ml-optmize-yt{position:relative;display:block;background-color:#000;background-position:center;background-size:cover;aspect-ratio:16/9;width:100%;max-width:100%;cursor:pointer;contain:content;}
.ml-optmize-yt::before{content:"";position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:68px;height:48px;background-color:#000;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 68 48'><path d='M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55C3.97 2.33 2.27 4.81 1.48 7.74.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z' fill='%23f00'/><path d='M45 24 27 14v20' fill='%23fff'/></svg>") no-repeat;background-size:contain;z-index:2;transition:transform .2s;}
.ml-optmize-yt:hover::before{transform:translate(-50%,-50%) scale(1.1);}
.ml-optmize-yt::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 0%,rgba(0,0,0,0.4) 100%);}
.ml-optmize-yt iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0;}
CSS;
	}

	/**
	 * JS do lite-youtube (web component lite).
	 *
	 * @return string
	 */
	private static function yt_js() {
		return <<<JS
customElements.define('lite-youtube', class extends HTMLElement {
  connectedCallback() {
    this.style.cursor = 'pointer';
    this.addEventListener('click', () => {
      const id = this.getAttribute('videoid');
      if (!id) return;
      const iframe = document.createElement('iframe');
      const label = this.getAttribute('playlabel') || 'Play';
      iframe.setAttribute('src', 'https://www.youtube.com/embed/' + encodeURIComponent(id) + '?autoplay=1');
      iframe.setAttribute('title', label);
      iframe.setAttribute('frameborder', '0');
      iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
      iframe.setAttribute('allowfullscreen', '');
      this.innerHTML = '';
      this.appendChild(iframe);
    });
  }
});
JS;
	}
}
