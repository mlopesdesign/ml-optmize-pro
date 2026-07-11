<?php
/**
 * Lazy Render — atrasa a renderizacao de elementos off-screen via
 * IntersectionObserver (front-end JS). O HTML fica com placeholder minimo
 * ate o usuario chegar perto do elemento.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Lazy_Render {

	const HANDLE = 'ml-optmize-pro-lazy-render';

	/**
	 * Registra hooks.
	 */
	public static function register() {
		if ( is_admin() ) {
			return;
		}
		if ( ! ML_Optimize_Pro_Settings::is_on( 'lazy_render_enabled' ) ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Inicia buffer.
	 */
	public static function maybe_start_buffer() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( is_feed() || is_preview() ) {
			return;
		}
		ob_start( array( __CLASS__, 'on_buffer_end' ) );
	}

	/**
	 * Enfileira o JS.
	 */
	public static function enqueue() {
		wp_register_script( self::HANDLE, '', array(), ML_OPTIMIZE_PRO_VERSION, true );
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script( self::HANDLE, self::js() );
	}

	/**
	 * Processa HTML — substitui blocos por placeholders.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function on_buffer_end( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 200 ) {
			return $html;
		}
		if ( function_exists( 'http_response_code' ) && 200 !== http_response_code() ) {
			return $html;
		}
		$selectors = self::get_selectors();
		if ( empty( $selectors ) ) {
			return $html;
		}
		$idx = 0;
		foreach ( $selectors as $selector ) {
			$selector = trim( (string) $selector );
			if ( '' === $selector ) {
				continue;
			}
			// ID.
			if ( 0 === strpos( $selector, '#' ) ) {
				$id = substr( $selector, 1 );
				$pattern = '#<([a-z0-9]+)([^>]*?)id=["\']' . preg_quote( $id, '#' ) . '["\']([^>]*?)>(.*?)</\1>#is';
				$html = preg_replace_callback( $pattern, function( $m ) use ( &$idx ) {
					$idx++;
					$open = '<' . $m[1] . $m[2] . 'id="' . $m[2] . '"' . $m[3] . '>';
					// Reconstroi: troca por placeholder.
					return '<template class="ml-optmize-lazy-render" data-idx="' . $idx . '">' . $m[0] . '</template>';
				}, $html, 1 );
			} elseif ( 0 === strpos( $selector, '.' ) ) {
				$cls = preg_quote( substr( $selector, 1 ), '#' );
				$pattern = '#<([a-z0-9]+)([^>]*?)\bclass=["\']([^"\']*\b' . $cls . '\b[^"\']*)["\']([^>]*?)>(.*?)</\1>#is';
				$html = preg_replace_callback( $pattern, function( $m ) use ( &$idx ) {
					$idx++;
					return '<template class="ml-optmize-lazy-render" data-idx="' . $idx . '">' . $m[0] . '</template>';
				}, $html, 1 );
			}
		}
		return $html;
	}

	/**
	 * Retorna seletores configurados.
	 *
	 * @return array
	 */
	private static function get_selectors() {
		$raw = (string) ML_Optimize_Pro_Settings::get( 'lazy_render_selectors', '' );
		return array_filter( array_map( 'trim', preg_split( "/[\r\n]+/", $raw ) ) );
	}

	/**
	 * JS front-end.
	 *
	 * @return string
	 */
	public static function js() {
		return <<<JS
(function(){
  if (typeof IntersectionObserver === 'undefined') return;
  var templates = document.querySelectorAll('template.ml-optmize-lazy-render');
  if (!templates.length) return;
  var observer = new IntersectionObserver(function(entries){
    entries.forEach(function(entry){
      if (entry.isIntersecting) {
        var tpl = entry.target;
        var docFrag = tpl.content.cloneNode(true);
        tpl.parentNode.insertBefore(docFrag, tpl);
        tpl.parentNode.removeChild(tpl);
        observer.unobserve(tpl);
      }
    });
  }, { rootMargin: '200px 0px' });
  templates.forEach(function(t){ observer.observe(t); });
})();
JS;
	}
}
