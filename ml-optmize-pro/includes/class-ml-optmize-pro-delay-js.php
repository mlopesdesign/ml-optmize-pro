<?php
/**
 * Delay JS Execution — adia a execucao de scripts ate interacao do usuario.
 *
 * Estrategia (padrao WP Rocket): captura o HTML, encontra <script src> e inline,
 * converte src em type="text/plain" + data-original-src, e adiciona um loader JS
 * que restaura quando ha interacao.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Delay_JS {

	const LOADER_HANDLE = 'ml-optmize-pro-delay-loader';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		if ( ! ML_Optimize_Pro_Settings::is_on( 'delay_js' ) ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_loader' ) );
	}

	/**
	 * Inicia buffer.
	 */
	public static function maybe_start_buffer() {
		if ( ! self::should_process() ) {
			return;
		}
		ob_start( array( __CLASS__, 'on_buffer_end' ) );
	}

	/**
	 * Enfileira o loader JS.
	 */
	public static function enqueue_loader() {
		wp_register_script( self::LOADER_HANDLE, '', array(), ML_OPTIMIZE_PRO_VERSION, true );
		wp_enqueue_script( self::LOADER_HANDLE );
		wp_add_inline_script( self::LOADER_HANDLE, self::loader_js() );
	}

	/**
	 * Decide se processa.
	 *
	 * @return bool
	 */
	private static function should_process() {
		if ( ! ML_Optimize_Pro_Settings::is_on( 'delay_js' ) ) {
			return false;
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return false;
		}
		if ( is_feed() || is_preview() || is_user_logged_in() ) {
			return false;
		}
		return true;
	}

	/**
	 * Processa buffer final.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function on_buffer_end( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 100 ) {
			return $html;
		}
		if ( function_exists( 'http_response_code' ) && 200 !== http_response_code() ) {
			return $html;
		}
		$exclusions = self::get_exclusions();
		// Padrao: nao atrasa jquery nem scripts com id ml-optmize.
		$default_excl = array( 'jquery', 'jquery-core', 'jquery-migrate', 'ml-optmize' );
		$exclusions   = array_unique( array_merge( $default_excl, $exclusions ) );
		// 1) Inline scripts: <script>...</script> sem src.
		$html = preg_replace_callback( '#<script(?![^>]*\bsrc=)(?![^>]*type=["\']module["\'])(?![^>]*type=["\'](?:application/(?:ld\+json|json))["\'])([^>]*)>(.*?)</script>#is', function( $m ) use ( $exclusions ) {
			$body = $m[2];
			// Se marcado explicitamente para nao atrasar.
			if ( false !== stripos( $m[1], 'data-no-delay' ) ) {
				return $m[0];
			}
			foreach ( $exclusions as $excl ) {
				if ( false !== stripos( $body, $excl ) || false !== stripos( $m[1], $excl ) ) {
					return $m[0];
				}
			}
			$hash = md5( $body );
			return '<script type="text/plain" class="ml-optmize-delay-script" data-ml-optmize-hash="' . esc_attr( $hash ) . '">' . $body . '</script>';
		}, $html );
		// 2) Scripts com src: troca src por data-original-src.
		$html = preg_replace_callback( '#<script([^>]*)\bsrc=["\']([^"\']+)["\']([^>]*)></script>#i', function( $m ) use ( $exclusions ) {
			$attrs  = $m[1] . $m[3];
			$src    = $m[2];
			// Se ja tem defer/async/type=module, mantem.
			if ( false !== stripos( $attrs, ' async' ) || false !== stripos( $attrs, ' defer' ) || false !== stripos( $attrs, 'type="module"' ) || false !== stripos( $attrs, 'data-no-delay' ) ) {
				return $m[0];
			}
			foreach ( $exclusions as $excl ) {
				if ( false !== stripos( $src, $excl ) ) {
					return $m[0];
				}
			}
			$attrs = preg_replace( '/\s+src=/', ' data-ml-optmize-src=', $attrs, 1 );
			$attrs = ' type="text/plain" class="ml-optmize-delay-script"' . $attrs;
			return '<script' . $attrs . '></script>';
		}, $html );
		return $html;
	}

	/**
	 * Retorna lista de exclusoes parseada.
	 *
	 * @return array
	 */
	private static function get_exclusions() {
		$raw = (string) ML_Optimize_Pro_Settings::get( 'delay_js_exclusions', '' );
		$list = array_filter( array_map( 'trim', preg_split( "/[\r\n]+/", $raw ) ) );
		return array_map( 'strtolower', $list );
	}

	/**
	 * Loader JS que restaura os scripts ao detectar interacao.
	 *
	 * @return string
	 */
	public static function loader_js() {
		return <<<JS
(function(){
  if (typeof window === 'undefined') return;
  var events = ['mousemove','keydown','wheel','touchstart','touchmove','scroll','click'];
  var restore = function(){
    var scripts = document.querySelectorAll('script.ml-optmize-delay-script');
    for (var i = 0; i < scripts.length; i++) {
      var s = scripts[i];
      if (s.dataset.mlOptmizeSrc) {
        var ns = document.createElement('script');
        var attrs = s.attributes;
        for (var j = 0; j < attrs.length; j++) {
          var a = attrs[j];
          if (a.name.indexOf('ml-optmize-') !== -1) continue;
          if (a.name === 'class') { ns.className = a.value.replace(/ml-optmize-delay-script/g,'').trim(); continue; }
          if (a.name === 'type' && a.value === 'text/plain') continue;
          try { ns.setAttribute(a.name, a.value); } catch(e){}
        }
        ns.src = s.dataset.mlOptmizeSrc;
        s.parentNode.insertBefore(ns, s);
        s.parentNode.removeChild(s);
      } else {
        var ns2 = document.createElement('script');
        ns2.text = s.textContent;
        s.parentNode.insertBefore(ns2, s);
        s.parentNode.removeChild(s);
      }
    }
    document.removeEventListener('DOMContentLoaded', restore);
    events.forEach(function(ev){ window.removeEventListener(ev, restore, { passive: true }); });
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', restore);
  }
  events.forEach(function(ev){ window.addEventListener(ev, restore, { passive: true, once: false }); });
  setTimeout(restore, 4000);
})();
JS;
	}
}
