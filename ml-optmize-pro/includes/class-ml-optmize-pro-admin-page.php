<?php
/**
 * Admin Page — render das telas premium (Performance Hub + tabs).
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ML_Optimize_Pro_Admin_Page {

	/**
	 * Renderiza dashboard (Performance Hub).
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissao negada.', 'ml-optmize-pro' ) );
		}
		$score   = ML_Optimize_Pro_Performance_Hub::get_score();
		$modules = ML_Optimize_Pro_Performance_Hub::get_active_modules();
		$db      = ML_Optimize_Pro_Performance_Hub::get_db_stats();
		$logs    = ML_Optimize_Pro_Logs::get( 8 );
		$last_cleanup = get_option( 'ml_optimize_pro_last_cleanup', '' );
		$last_preload = get_option( 'ml_optimize_pro_last_preload', '' );
		?>
		<div class="wrap ml-optmize-pro-admin">
			<?php self::render_header( 'Performance Hub', 'Score estimado de otimizacao, modulos ativos e atalhos rapidos.' ); ?>
			<?php self::render_tabs( 'dashboard' ); ?>

			<div class="mlopt-grid">
				<div class="mlopt-card">
					<h2>Score estimado</h2>
					<div class="mlopt-score-display">
						<div class="mlopt-score-value-big"><?php echo (int) $score['percentage']; ?><span>%</span></div>
						<div class="mlopt-score-label-big">Otimizacao estimada</div>
					</div>
					<p class="description">Score calculado a partir dos modulos ativos e da configuracao atual.</p>
				</div>

				<div class="mlopt-card">
					<h2>Modulos ativos</h2>
					<p class="description"><?php echo (int) $modules['active']; ?> de <?php echo (int) $modules['total']; ?> modulos ligados.</p>
					<div class="mlopt-modules-grid">
						<?php foreach ( $modules['modules'] as $name => $on ) : ?>
							<div class="mlopt-module-pill <?php echo $on ? 'is-on' : 'is-off'; ?>">
								<span class="dashicons <?php echo $on ? 'dashicons-yes' : 'dashicons-minus'; ?>"></span>
								<?php echo esc_html( self::module_label( $name ) ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="mlopt-card">
					<h2>Atalhos rapidos</h2>
					<div class="mlopt-shortcuts">
						<button class="button button-primary mlopt-btn" data-action="purge-cache">
							<span class="dashicons dashicons-trash"></span> Purgar cache
						</button>
						<button class="button mlopt-btn" data-action="run-db">
							<span class="dashicons dashicons-database"></span> Limpar banco agora
						</button>
						<button class="button mlopt-btn" data-action="check-update">
							<span class="dashicons dashicons-update"></span> Checar update
						</button>
						<button class="button mlopt-btn" data-action="export-settings">
							<span class="dashicons dashicons-download"></span> Exportar config
						</button>
					</div>
				</div>

				<div class="mlopt-card">
					<h2>Banco de dados</h2>
					<ul class="mlopt-stats">
						<li><span>Revisoes</span><strong><?php echo (int) $db['revisions']; ?></strong></li>
						<li><span>Autodrafts</span><strong><?php echo (int) $db['autodrafts']; ?></strong></li>
						<li><span>Lixeira</span><strong><?php echo (int) $db['trash']; ?></strong></li>
						<li><span>Spam</span><strong><?php echo (int) $db['spam']; ?></strong></li>
						<li><span>Transients</span><strong><?php echo (int) $db['transients']; ?></strong></li>
						<li><span>Tamanho DB</span><strong><?php echo esc_html( $db['db_size'] ); ?></strong></li>
					</ul>
					<?php if ( $last_cleanup ) : ?>
						<p class="description">Ultimo cleanup: <?php echo esc_html( $last_cleanup ); ?></p>
					<?php endif; ?>
				</div>

				<div class="mlopt-card">
					<h2>Atividade recente</h2>
					<?php if ( empty( $logs ) ) : ?>
						<p class="description">Nenhuma atividade registrada ainda. Ative os modulos para comecar a ver logs.</p>
					<?php else : ?>
						<ul class="mlopt-log-list">
							<?php foreach ( $logs as $entry ) : ?>
								<li class="mlopt-log-entry mlopt-log-<?php echo esc_attr( $entry['level'] ); ?>">
									<span class="mlopt-log-time"><?php echo esc_html( $entry['time'] ); ?></span>
									<span class="mlopt-log-module"><?php echo esc_html( $entry['module'] ); ?></span>
									<span class="mlopt-log-msg"><?php echo esc_html( $entry['message'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="description">Ultimo preload: <?php echo esc_html( $last_preload ? $last_preload : '—' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Roteador de tabs — le $_GET['page'] e mapeia pra tab.
	 */
	public static function render_tab_router() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissao negada.', 'ml-optmize-pro' ) );
		}
		// Mapa page_slug -> tab_slug (slugs limpos, sem &).
		$page_map = array(
			'ml-optmize-pro'                => 'dashboard',
			'ml-optmize-pro-cache'          => 'cache',
			'ml-optmize-pro-files'          => 'files',
			'ml-optmize-pro-lazy'           => 'lazy',
			'ml-optmize-pro-script-manager' => 'script_manager',
			'ml-optmize-pro-bloat'          => 'bloat',
			'ml-optmize-pro-fonts'          => 'fonts',
			'ml-optmize-pro-preload'        => 'preload',
			'ml-optmize-pro-database'       => 'database',
			'ml-optmize-pro-heartbeat'      => 'heartbeat',
			'ml-optmize-pro-cdn'            => 'cdn',
			'ml-optmize-pro-speculation'    => 'speculation',
			'ml-optmize-pro-logs'           => 'logs',
			'ml-optmize-pro-settings'       => 'settings',
		);
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( isset( $page_map[ $page ] ) ) {
			$tab = $page_map[ $page ];
		} elseif ( ! empty( $_GET['tab'] ) ) {
			$tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
		} else {
			$tab = 'dashboard';
		}
		$method = 'render_tab_' . $tab;
		if ( method_exists( __CLASS__, $method ) ) {
			call_user_func( array( __CLASS__, $method ) );
			return;
		}
		self::render_dashboard();
	}

	/**
	 * Tab: Cache.
	 */
	public static function render_tab_cache() {
		$s = ML_Optimize_Pro_Settings::all();
		self::render_header( 'Cache', 'Page cache em disco, browser cache e cache preload.' );
		self::render_tabs( 'cache' );
		?>
		<form method="post" class="mlopt-form" data-module="cache">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Page cache' ); ?>
				<?php self::field_toggle( 'cache_enabled', 'Ativar page cache', 'Salva HTML estatico em /wp-content/cache/ml-optmize-pro/pages/. Recomenda-se ligado.' ); ?>
				<?php self::field_toggle( 'cache_mobile', 'Cache separado para mobile', 'Gera versao mobile do cache (recomendado).' ); ?>
				<?php self::field_toggle( 'cache_logged_users', 'Cache para usuarios logados', 'Desligado por padrao (recomendado).' ); ?>
				<?php self::field_toggle( 'cache_preload', 'Cache preload automatico', 'Pre-aquece o cache via sitemap + posts recentes.' ); ?>
				<?php self::field_toggle( 'cache_purge_on_update', 'Purgar cache ao atualizar post', 'Limpa o cache do post/arquivo sempre que um post muda.' ); ?>
				<?php self::field_text( 'cache_lifespan', 'Lifespan (segundos)', '0 = infinito. Padrao 36000 (10h).', 'number' ); ?>
				<?php self::field_textarea( 'cache_excluded_urls', 'URLs excluidas (uma por linha)', 'Padrao: /cart /checkout /my-account /wp-admin /wp-login.php' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'Browser cache' ); ?>
				<?php self::field_toggle( 'browser_cache_enabled', 'Ativar browser cache', 'Adiciona headers Expires e Cache-Control via .htaccess.' ); ?>
				<?php self::field_text( 'browser_cache_expiry', 'Expiracao (segundos)', 'Padrao 31536000 (1 ano).', 'number' ); ?>
				<p class="description">Para Nginx, copie o snippet mostrado em <a href="#" data-show="nginx">regras Nginx</a>.</p>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'cache' ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: File Optimization.
	 */
	public static function render_tab_files() {
		self::render_header( 'File Optimization', 'Minify, combine, defer, delay e remove unused CSS.' );
		self::render_tabs( 'files' );
		?>
		<form method="post" class="mlopt-form" data-module="files">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Minify' ); ?>
				<?php self::field_toggle( 'minify_html', 'Minify HTML', 'Remove comentarios e whitespace.' ); ?>
				<?php self::field_toggle( 'minify_css', 'Minify CSS', 'Reduz tamanho do CSS.' ); ?>
				<?php self::field_toggle( 'minify_js', 'Minify JS', 'Reduz tamanho do JS (regex leve).' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'Combine' ); ?>
				<?php self::field_toggle( 'combine_css', 'Combine CSS', 'Agrupa CSS em 1 arquivo. Recomendado off em HTTP/2.' ); ?>
				<?php self::field_toggle( 'combine_js', 'Combine JS', 'Agrupa JS em 1 arquivo. Recomendado off em HTTP/2.' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'JavaScript' ); ?>
				<?php self::field_toggle( 'defer_js', 'Defer JavaScript', 'Adiciona defer em scripts nao criticos.' ); ?>
				<?php self::field_toggle( 'delay_js', 'Delay JS execution', 'Aguarda interacao do usuario. Pode quebrar sites com scripts sincronos.' ); ?>
				<?php self::field_textarea( 'delay_js_exclusions', 'Exclusoes (uma keyword por linha)', 'Palavras em src/conteudo que NAO devem ser atrasadas.' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'CSS' ); ?>
				<?php self::field_toggle( 'remove_unused_css', 'Remove Unused CSS (async)', 'Carrega CSS sem bloquear render via preload + onload.' ); ?>
				<?php self::field_toggle( 'load_css_async', 'Load CSS Asynchronously', 'Metodo alternativo para load async.' ); ?>
				<?php self::field_textarea( 'unused_css_safelist', 'Safelist (URLs que NAO devem ser atrasadas)', 'Padrao: wp-admin wp-includes fonts.googleapis.com' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'files' ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Lazy Load.
	 */
	public static function render_tab_lazy() {
		self::render_header( 'Lazy Load', 'Imagens, iframes, videos e lazy render de elementos.' );
		self::render_tabs( 'lazy' );
		?>
		<form method="post" class="mlopt-form" data-module="lazy">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Lazy Load' ); ?>
				<?php self::field_toggle( 'lazy_images', 'Lazy load imagens', 'Adiciona loading=lazy em <img>.' ); ?>
				<?php self::field_toggle( 'lazy_iframes', 'Lazy load iframes', 'Adiciona loading=lazy em <iframe>.' ); ?>
				<?php self::field_toggle( 'lazy_videos', 'Lazy load videos', 'Adiciona preload=none em <video>.' ); ?>
				<?php self::field_toggle( 'lazy_youtube', 'Lazy YouTube (lite preview)', 'Substitui iframe do YouTube por lite preview com poster.' ); ?>
				<?php self::field_toggle( 'lazy_bg_images', 'Lazy CSS background images', 'Via IntersectionObserver (requer config manual).' ); ?>
				<?php self::field_text( 'lazy_skip_first', 'Pular as primeiras N imagens', 'Recomendado 1 (imagem LCP fica sem lazy).', 'number' ); ?>
				<?php self::field_textarea( 'lazy_exclusions', 'Exclusoes (substring em src)', 'Ex: logo, hero, banner' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'Lazy Render' ); ?>
				<?php self::field_toggle( 'lazy_render_enabled', 'Ativar Lazy Render', 'Adia a renderizacao de elementos off-screen via IntersectionObserver.' ); ?>
				<?php self::field_textarea( 'lazy_render_selectors', 'Seletores CSS (um por linha)', 'Ex: #comments, #footer, .site-sidebar' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'lazy' ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Script Manager.
	 */
	public static function render_tab_script_manager() {
		self::render_header( 'Script Manager', 'Desabilita scripts/CSS por URL, post type ou post ID. Test mode recomendado.' );
		self::render_tabs( 'script_manager' );
		$rules = ML_Optimize_Pro_Script_Manager::get_rules();
		?>
		<form method="post" class="mlopt-form" data-module="script_manager">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Configuracao' ); ?>
				<?php self::field_toggle( 'script_manager_enabled', 'Ativar Script Manager', 'Quando ativo, aplica as rules abaixo.' ); ?>
				<?php self::field_toggle( 'script_manager_test_mode', 'Test mode', 'Mostra o efeito somente para admins logados. Recomendado.' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'Regras' ); ?>
				<p class="description">Adicione regras para desabilitar scripts/CSS. Handles separados por virgula. Para regex, comece com / (ex: /wp-content/plugins/contact-form-7/).</p>
				<div id="mlopt-rules" class="mlopt-rules"></div>
				<button type="button" class="button" id="mlopt-add-rule">+ Adicionar regra</button>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'script_manager' ); ?>
		</form>

		<script>
		window.mloptRules = <?php echo wp_json_encode( $rules ); ?>;
		</script>
		<?php
	}

	/**
	 * Tab: Bloat Remover.
	 */
	public static function render_tab_bloat() {
		self::render_header( 'Bloat Remover', 'Desabilita features do WP que custam performance.' );
		self::render_tabs( 'bloat' );
		?>
		<form method="post" class="mlopt-form" data-module="bloat">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Frontend' ); ?>
				<?php self::field_toggle( 'bloat_emojis', 'Desabilitar emojis', 'Remove script e CSS de emojis do WP.' ); ?>
				<?php self::field_toggle( 'bloat_embeds', 'Desabilitar embeds', 'Remove oEmbed e scripts de embed.' ); ?>
				<?php self::field_toggle( 'bloat_dashicons', 'Desabilitar dashicons (frontend)', 'Remove dashicons para visitantes deslogados.' ); ?>
				<?php self::field_toggle( 'bloat_jquery_migrate', 'Remover jQuery Migrate', 'Moderno nao precisa.' ); ?>
				<?php self::field_toggle( 'bloat_query_strings', 'Remover query strings', 'Remove ?ver= dos assets estaticos.' ); ?>
				<?php self::field_toggle( 'bloat_wp_version', 'Remover versao do WP', 'Esconde a versao no HTML.' ); ?>
				<?php self::field_toggle( 'bloat_rsd_link', 'Remover RSD link', 'Editoriais nao precisam.' ); ?>
				<?php self::field_toggle( 'bloat_shortlink', 'Remover shortlink', 'Visual no head.' ); ?>
				<?php self::field_toggle( 'bloat_rss_feeds', 'Desabilitar RSS feeds', 'Atencao: bloqueia acesso aos feeds.' ); ?>
				<?php self::field_toggle( 'bloat_block_library', 'Remover Block Library CSS', 'Para sites classicos que nao usam Gutenberg.' ); ?>
				<?php self::field_toggle( 'bloat_gutenberg_frontend', 'Remover Gutenberg frontend', 'Remove global styles inline.' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'API' ); ?>
				<?php self::field_toggle( 'bloat_xmlrpc', 'Desabilitar XML-RPC', 'Reduz superficie de ataque.' ); ?>
				<?php self::field_toggle( 'bloat_rest_api_users', 'Esconder REST users para anonimos', 'Bloqueia /wp-json/wp/v2/users.' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'Plugins' ); ?>
				<?php self::field_toggle( 'bloat_woocommerce_bloat', 'Remover WooCommerce bloat', 'Cart fragments, estilos em paginas nao-woo.' ); ?>
				<?php self::field_toggle( 'bloat_google_maps', 'Desabilitar Google Maps global', 'Ative por excecao via Script Manager.' ); ?>
				<?php self::field_toggle( 'bloat_cf7', 'Desabilitar CF7 global', 'Ative por excecao via Script Manager.' ); ?>
				<?php self::field_toggle( 'bloat_gravityforms', 'Desabilitar Gravity Forms global', 'Ative por excecao via Script Manager.' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'bloat' ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Fonts.
	 */
	public static function render_tab_fonts() {
		self::render_header( 'Fonts', 'Self-host Google Fonts, preload e font-display swap.' );
		self::render_tabs( 'fonts' );
		?>
		<form method="post" class="mlopt-form" data-module="fonts">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Google Fonts' ); ?>
				<?php self::field_toggle( 'fonts_self_host', 'Self-host Google Fonts', 'Baixa as fontes para o servidor local e remove requests externos.' ); ?>
				<?php self::field_toggle( 'fonts_combine', 'Combine Google Fonts', 'Combina multiplas chamadas em 1 request.' ); ?>
				<?php self::field_toggle( 'fonts_preload', 'Preload fonts', 'Adiciona <link rel=preload as=font> para as URLs abaixo.' ); ?>
				<?php self::field_textarea( 'fonts_preload_urls', 'URLs para preload (uma por linha)', '/wp-content/uploads/ml-optmize-pro-fonts/font_abc.woff2' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'Display & Fallbacks' ); ?>
				<?php self::field_toggle( 'fonts_display_swap', 'font-display: swap automatico', 'Evita FOIT (flash of invisible text).' ); ?>
				<?php self::field_toggle( 'fonts_system_first', 'System fonts primeiro', 'Mostra fontes do sistema antes do swap.' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'fonts' ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Preload.
	 */
	public static function render_tab_preload() {
		self::render_header( 'Preload & Resource Hints', 'Preload de recursos criticos, DNS prefetch, preconnect.' );
		self::render_tabs( 'preload' );
		?>
		<form method="post" class="mlopt-form" data-module="preload">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Critical Resources' ); ?>
				<?php self::field_toggle( 'preload_enabled', 'Ativar preload', 'Ativa o output das tags abaixo.' ); ?>
				<?php self::field_textarea( 'critical_resources', 'Recursos criticos (formato: URL|as|type)', 'Ex: /wp-content/themes/x/style.css|style|text/css' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'DNS Prefetch' ); ?>
				<?php self::field_toggle( 'dns_prefetch_enabled', 'Ativar DNS prefetch', 'Resolve DNS antecipadamente.' ); ?>
				<?php self::field_textarea( 'dns_prefetch_hosts', 'Hosts (um por linha, sem //)', 'fonts.googleapis.com' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'Preconnect' ); ?>
				<?php self::field_toggle( 'preconnect_enabled', 'Ativar preconnect', 'Estabelece conexao TLS + TCP antecipada.' ); ?>
				<?php self::field_textarea( 'preconnect_hosts', 'Hosts (um por linha, sem //)', 'fonts.gstatic.com' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'preload' ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Database.
	 */
	public static function render_tab_database() {
		self::render_header( 'Database', 'Limpeza automatica do banco + estatisticas.' );
		self::render_tabs( 'database' );
		$db = ML_Optimize_Pro_Performance_Hub::get_db_stats();
		?>
		<form method="post" class="mlopt-form" data-module="database">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Estatisticas atuais' ); ?>
				<ul class="mlopt-stats">
					<li><span>Revisoes</span><strong><?php echo (int) $db['revisions']; ?></strong></li>
					<li><span>Autodrafts</span><strong><?php echo (int) $db['autodrafts']; ?></strong></li>
					<li><span>Lixeira</span><strong><?php echo (int) $db['trash']; ?></strong></li>
					<li><span>Spam</span><strong><?php echo (int) $db['spam']; ?></strong></li>
					<li><span>Transients</span><strong><?php echo (int) $db['transients']; ?></strong></li>
					<li><span>Orphan meta</span><strong><?php echo (int) $db['orphan_meta']; ?></strong></li>
					<li><span>Tamanho DB</span><strong><?php echo esc_html( $db['db_size'] ); ?></strong></li>
				</ul>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'Cleanup automatico' ); ?>
				<?php self::field_toggle( 'db_cleanup_enabled', 'Ativar cleanup automatico', 'Roda diariamente/semanalmente via cron.' ); ?>
				<?php self::field_select( 'db_cleanup_schedule', 'Frequencia', array( 'daily' => 'Diario', 'weekly' => 'Semanal' ) ); ?>
				<?php self::field_text( 'db_revisions_limit', 'Limite de revisoes por post', 'Padrao 5.', 'number' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'Limpar agora' ); ?>
				<?php self::field_toggle( 'db_cleanup_revisions', 'Limpar revisoes', 'Remove posts do tipo revision.' ); ?>
				<?php self::field_toggle( 'db_cleanup_autodrafts', 'Limpar autodrafts', 'Remove posts auto-draft.' ); ?>
				<?php self::field_toggle( 'db_cleanup_trash', 'Limpar lixeira', 'Remove posts com status trash.' ); ?>
				<?php self::field_toggle( 'db_cleanup_spam', 'Limpar spam comments', 'Remove comments marcados como spam.' ); ?>
				<?php self::field_toggle( 'db_cleanup_transients', 'Limpar transients expirados', 'Remove transients vencidos.' ); ?>
				<?php self::field_toggle( 'db_cleanup_optimize', 'Otimizar tabelas (OPTIMIZE TABLE)', 'Roda no manual.' ); ?>
				<button type="button" class="button button-primary mlopt-btn" data-action="run-db">
					<span class="dashicons dashicons-database"></span> Rodar cleanup agora
				</button>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'database' ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Heartbeat.
	 */
	public static function render_tab_heartbeat() {
		self::render_header( 'Heartbeat', 'Controle da API Heartbeat do WordPress.' );
		self::render_tabs( 'heartbeat' );
		?>
		<form method="post" class="mlopt-form" data-module="heartbeat">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Controle' ); ?>
				<?php self::field_toggle( 'heartbeat_frontend', 'Heartbeat no frontend', 'Geralmente desnecessario.' ); ?>
				<?php self::field_toggle( 'heartbeat_backend', 'Heartbeat no admin', 'Se ativo, controla a frequencia abaixo.' ); ?>
				<?php self::field_toggle( 'heartbeat_editor', 'Heartbeat no editor', 'Auto-save usa heartbeat. Desligar em post types com revisoes.' ); ?>
				<?php self::field_text( 'heartbeat_frequency', 'Frequencia (segundos)', 'Padrao 60. Minimo 15.', 'number' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'heartbeat' ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: CDN.
	 */
	public static function render_tab_cdn() {
		self::render_header( 'CDN', 'Reescreve URLs estaticas para um CDN configurado.' );
		self::render_tabs( 'cdn' );
		?>
		<form method="post" class="mlopt-form" data-module="cdn">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Configuracao' ); ?>
				<?php self::field_toggle( 'cdn_enabled', 'Ativar CDN', 'Reescreve URLs estaticas para o CDN.' ); ?>
				<?php self::field_text( 'cdn_url', 'URL do CDN', 'Ex: https://cdn.seusite.com' ); ?>
				<?php self::field_textarea( 'cdn_excludes', 'Caminhos excluidos (um por linha)', 'Padrao: wp-content/uploads wp-includes' ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'cdn' ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Speculation.
	 */
	public static function render_tab_speculation() {
		self::render_header( 'Speculation Rules', 'Pre-renderiza links ao hover usando a Speculation Rules API (Chrome 109+).' );
		self::render_tabs( 'speculation' );
		?>
		<form method="post" class="mlopt-form" data-module="speculation">
			<?php self::render_nonce_field(); ?>
			<?php self::module_open_card( 'Speculation' ); ?>
				<?php self::field_toggle( 'speculation_enabled', 'Ativar Speculation Rules', 'Otimiza perceived performance.' ); ?>
				<?php self::field_select( 'speculation_eagerness', 'Eagerness', array(
					'immediate'     => 'Immediate (pre-renderiza ao hover)',
					'moderate'      => 'Moderate (pre-renderiza ao hover com 200ms)',
					'conservative'  => 'Conservative (pre-renderiza ao click)',
				) ); ?>
			<?php self::module_close_card(); ?>

			<?php self::module_actions( 'speculation' ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Logs.
	 */
	public static function render_tab_logs() {
		self::render_header( 'Logs', 'Atividade dos modulos. Max 200 entradas.' );
		self::render_tabs( 'logs' );
		$logs = ML_Optimize_Pro_Logs::get( 200 );
		?>
		<div class="mlopt-card">
			<div class="mlopt-card-actions">
				<button class="button mlopt-btn" data-action="clear-logs">
					<span class="dashicons dashicons-trash"></span> Limpar logs
				</button>
			</div>
			<?php if ( empty( $logs ) ) : ?>
				<p class="description">Nenhum log registrado ainda.</p>
			<?php else : ?>
				<table class="widefat mlopt-log-table">
					<thead>
						<tr><th>Quando</th><th>Modulo</th><th>Nivel</th><th>Mensagem</th></tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['time'] ); ?></td>
								<td><?php echo esc_html( $entry['module'] ); ?></td>
								<td><span class="mlopt-badge mlopt-badge-<?php echo esc_attr( $entry['level'] ); ?>"><?php echo esc_html( $entry['level'] ); ?></span></td>
								<td><?php echo esc_html( $entry['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Tab: Settings.
	 */
	public static function render_tab_settings() {
		self::render_header( 'Settings', 'Exportar / importar / resetar / auto-update via GitHub.' );
		self::render_tabs( 'settings' );
		?>
		<form method="post" class="mlopt-form" data-module="settings">
			<?php self::render_nonce_field(); ?>
			<input type="hidden" name="action" value="save_general">
			<?php self::module_open_card( 'Updater (GitHub)' ); ?>
				<?php self::field_text( 'updater_github_user', 'Usuario GitHub', 'Dono do repositorio oficial.', 'text' ); ?>
				<?php self::field_text( 'updater_github_repo', 'Repositorio GitHub', 'Nome do repo.', 'text' ); ?>
				<button type="button" class="button mlopt-btn" data-action="check-update">
					<span class="dashicons dashicons-update"></span> Checar update agora
				</button>
			<?php self::module_close_card(); ?>

			<?php self::module_open_card( 'Import / Export / Reset' ); ?>
				<button type="button" class="button mlopt-btn" data-action="export-settings">
					<span class="dashicons dashicons-download"></span> Exportar config (JSON)
				</button>
				<button type="button" class="button mlopt-btn" data-action="import-settings">
					<span class="dashicons dashicons-upload"></span> Importar config
				</button>
				<button type="button" class="button button-link-delete mlopt-btn" data-action="reset-settings">
					<span class="dashicons dashicons-undo"></span> Resetar tudo
				</button>
			<?php self::module_close_card(); ?>

			<?php submit_button( 'Salvar configuracoes gerais' ); ?>
		</form>
		<?php
	}

	/* ===================== HELPERS ===================== */

	/**
	 * Renderiza o hero (padrao ML, replicado de mlab-admin-hero).
	 *
	 * @param string $title Titulo.
	 * @param string $desc  Descricao.
	 */
	public static function render_header( $title, $desc ) {
		?>
		<div class="mlopt-admin-hero">
			<div class="mlopt-admin-hero-brand">
				<div class="mlopt-admin-hero-mark"><img src="<?php echo esc_url( ML_OPTIMIZE_PRO_URL . 'assets/images/logo-wordpress.png' ); ?>" alt="ML Lopes Design"></div>
				<div class="mlopt-admin-hero-copy">
					<div class="mlopt-admin-eyebrow">ML Lopes Design · Optimize Pro</div>
					<h1><?php echo esc_html( $title ); ?></h1>
					<p class="mlopt-admin-intro"><?php echo esc_html( $desc ); ?></p>
				</div>
			</div>
			<div class="mlopt-admin-hero-meta">
				<div class="mlopt-version-badge">v<?php echo esc_html( ML_OPTIMIZE_PRO_VERSION ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderiza as abas.
	 *
	 * @param string $active Aba ativa.
	 */
	public static function render_tabs( $active ) {
		$tabs = array(
			'dashboard'      => array( 'label' => 'Performance Hub', 'icon' => 'dashicons-performance', 'page' => 'ml-optmize-pro' ),
			'cache'          => array( 'label' => 'Cache', 'icon' => 'dashicons-update', 'page' => 'ml-optmize-pro-cache' ),
			'files'          => array( 'label' => 'File Optimization', 'icon' => 'dashicons-media-code', 'page' => 'ml-optmize-pro-files' ),
			'lazy'           => array( 'label' => 'Lazy Load', 'icon' => 'dashicons-images-alt2', 'page' => 'ml-optmize-pro-lazy' ),
			'script_manager' => array( 'label' => 'Script Manager', 'icon' => 'dashicons-editor-code', 'page' => 'ml-optmize-pro-script-manager' ),
			'bloat'          => array( 'label' => 'Bloat Remover', 'icon' => 'dashicons-dismiss', 'page' => 'ml-optmize-pro-bloat' ),
			'fonts'          => array( 'label' => 'Fonts', 'icon' => 'dashicons-editor-textcolor', 'page' => 'ml-optmize-pro-fonts' ),
			'preload'        => array( 'label' => 'Preload & Hints', 'icon' => 'dashicons-admin-site', 'page' => 'ml-optmize-pro-preload' ),
			'database'       => array( 'label' => 'Database', 'icon' => 'dashicons-database', 'page' => 'ml-optmize-pro-database' ),
			'heartbeat'      => array( 'label' => 'Heartbeat', 'icon' => 'dashicons-heart', 'page' => 'ml-optmize-pro-heartbeat' ),
			'cdn'            => array( 'label' => 'CDN', 'icon' => 'dashicons-cloud', 'page' => 'ml-optmize-pro-cdn' ),
			'speculation'    => array( 'label' => 'Speculation', 'icon' => 'dashicons-controls-forward', 'page' => 'ml-optmize-pro-speculation' ),
			'logs'           => array( 'label' => 'Logs', 'icon' => 'dashicons-list-view', 'page' => 'ml-optmize-pro-logs' ),
			'settings'       => array( 'label' => 'Settings', 'icon' => 'dashicons-admin-generic', 'page' => 'ml-optmize-pro-settings' ),
		);
		echo '<nav class="mlopt-admin-tabs">';
		foreach ( $tabs as $key => $tab ) {
			$url = admin_url( 'admin.php?page=' . $tab['page'] );
			$class = 'mlopt-admin-tab';
			if ( $key === $active ) {
				$class .= ' is-active';
			}
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">';
			echo '<span class="dashicons ' . esc_attr( $tab['icon'] ) . '"></span>';
			echo esc_html( $tab['label'] );
			echo '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Renderiza o nonce field.
	 */
	public static function render_nonce_field() {
		wp_nonce_field( ML_Optimize_Pro_Admin::NONCE_ACTION, ML_Optimize_Pro_Admin::NONCE_FIELD );
	}

	/**
	 * Abre um card de modulo.
	 *
	 * @param string $title Titulo.
	 */
	public static function module_open_card( $title ) {
		echo '<div class="mlopt-card">';
		echo '<h2>' . esc_html( $title ) . '</h2>';
	}

	/**
	 * Fecha um card de modulo.
	 */
	public static function module_close_card() {
		echo '</div>';
	}

	/**
	 * Renderiza os botoes de acao do modulo.
	 *
	 * @param string $module Modulo.
	 */
	public static function module_actions( $module ) {
		?>
		<div class="mlopt-card mlopt-actions">
			<button type="button" class="button button-primary mlopt-btn-save" data-save="<?php echo esc_attr( $module ); ?>">
				<span class="dashicons dashicons-saved"></span> Salvar modulo
			</button>
			<span class="mlopt-save-status"></span>
		</div>
		<?php
	}

	/**
	 * Field: toggle.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $desc  Desc.
	 */
	public static function field_toggle( $key, $label, $desc = '' ) {
		$val = (int) ML_Optimize_Pro_Settings::get( $key, 0 );
		?>
		<div class="mlopt-field mlopt-field-toggle">
			<label class="mlopt-toggle">
				<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $val, 1 ); ?>>
				<span class="mlopt-toggle-slider"></span>
			</label>
			<div class="mlopt-field-text">
				<div class="mlopt-field-label"><?php echo esc_html( $label ); ?></div>
				<?php if ( $desc ) : ?>
					<p class="description"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Field: text.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $desc  Desc.
	 * @param string $type  Type.
	 */
	public static function field_text( $key, $label, $desc = '', $type = 'text' ) {
		$val = ML_Optimize_Pro_Settings::get( $key, '' );
		?>
		<div class="mlopt-field">
			<label for="<?php echo esc_attr( $key ); ?>" class="mlopt-field-label"><?php echo esc_html( $label ); ?></label>
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $val ); ?>" class="regular-text mlopt-input">
			<?php if ( $desc ) : ?>
				<p class="description"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Field: textarea.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $desc  Desc.
	 */
	public static function field_textarea( $key, $label, $desc = '' ) {
		$val = ML_Optimize_Pro_Settings::get( $key, '' );
		if ( is_array( $val ) ) {
			$val = implode( "\n", $val );
		}
		?>
		<div class="mlopt-field">
			<label for="<?php echo esc_attr( $key ); ?>" class="mlopt-field-label"><?php echo esc_html( $label ); ?></label>
			<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="4" class="large-text mlopt-input"><?php echo esc_textarea( (string) $val ); ?></textarea>
			<?php if ( $desc ) : ?>
				<p class="description"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Field: select.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param array  $opts  Opcoes.
	 */
	public static function field_select( $key, $label, $opts ) {
		$val = ML_Optimize_Pro_Settings::get( $key, '' );
		?>
		<div class="mlopt-field">
			<label for="<?php echo esc_attr( $key ); ?>" class="mlopt-field-label"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="mlopt-input">
				<?php foreach ( $opts as $opt_key => $opt_label ) : ?>
					<option value="<?php echo esc_attr( $opt_key ); ?>" <?php selected( $val, $opt_key ); ?>><?php echo esc_html( $opt_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Retorna label de um modulo.
	 *
	 * @param string $name Modulo.
	 * @return string
	 */
	private static function module_label( $name ) {
		$labels = array(
			'cache'           => 'Page cache',
			'browser_cache'   => 'Browser cache',
			'minify'          => 'Minify',
			'defer_js'        => 'Defer JS',
			'delay_js'        => 'Delay JS',
			'remove_unused'   => 'Remove Unused CSS',
			'lazy_load'       => 'Lazy Load',
			'script_manager'  => 'Script Manager',
			'bloat'           => 'Bloat Remover',
			'fonts'           => 'Fonts',
			'db_cleanup'      => 'DB cleanup',
			'cdn'             => 'CDN',
			'speculation'     => 'Speculation',
		);
		return $labels[ $name ] ?? $name;
	}
}
