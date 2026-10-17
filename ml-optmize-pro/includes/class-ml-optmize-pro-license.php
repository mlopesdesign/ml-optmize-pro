<?php
/**
 * Licença do ML Optimize Pro (ML License Hub — tools.mlopesdesign.com.br).
 *
 * 1.1.0: a licença é emitida e conferida pelo site tools.mlopesdesign.com.br (cliente
 * ML_License_Client_V2). Aceita também a chave do Pacote Agência.
 *
 * Regras (plugin de desempenho):
 * - Sem teste/licença válidos, o cache e TODA a otimização continuam funcionando exatamente
 *   igual no site. Só o painel trava para gravar configurações e para as ações manuais
 *   (purgar cache, limpar banco, aplicar regras, importar/resetar, limpar logs, regras do
 *   Script Manager). Ler o painel continua liberado e nenhum dado é apagado.
 * - A licença NUNCA é consultada em requisição de front-end: esta classe só age no painel
 *   (admin, admin-ajax e admin-post do painel), na REST interna do painel e no cron diário.
 *   A conferência é local (token assinado, sem rede); a rede só é usada no cron diário,
 *   no 1º acesso ao painel (teste grátis automático) e nos botões da aba Licença.
 * - Quem atualiza recebe o teste grátis de 30 dias sozinho na 1ª visita ao painel.
 *
 * Este arquivo também é carregado pelo loader MU (wp-content/mu-plugins/ml-optmize-pro-mu.php),
 * antes dos plugins comuns: aqui só se declara a classe e se registram ganchos — nada roda
 * no carregamento.
 *
 * @package MLOptimizePro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'ML_Optimize_Pro_License' ) ) {
	return;
}

require_once __DIR__ . '/class-ml-license-client.php';

class ML_Optimize_Pro_License {

	const API_BASE    = 'https://tools.mlopesdesign.com.br/wp-json/mllh/v1';
	const PUBLIC_KEY  = 'VGZQp2X3RJMuDmQBlIEXS4fqsZD1CnFlIJgfVtY/pHo=';
	const PRODUCT_ID  = 'ml-optmize-pro';
	const BUY_URL     = 'https://tools.mlopesdesign.com.br/precos/';
	const ACCOUNT_URL = 'https://tools.mlopesdesign.com.br/minha-conta/';
	const AUTO_TRIAL  = 'ml_optimize_pro_auto_trial_tried';
	const TAB         = 'license';
	/**
	 * Página própria da Licença. As abas do painel (page=ml-optmize-pro&tab=x) caem todas no
	 * Performance Hub desde a 1.0.9 (rollback do motor de abas); por isso a Licença tem slug
	 * próprio e não depende desse roteamento.
	 */
	const PAGE        = 'ml-optmize-pro-license';

	/**
	 * Cliente do Hub (criado só quando preciso).
	 *
	 * @var ML_License_Client_V2|null
	 */
	private static $client = null;

	/**
	 * Resultado guardado durante a requisição (zera quando a licença muda).
	 *
	 * @var bool|null
	 */
	private static $locked = null;

	/* ------------------------------------------------------------------ base */

	/**
	 * Cliente do Hub. Agenda a renovação diária ao ser criado.
	 *
	 * @return ML_License_Client_V2
	 */
	public static function client() {
		if ( null === self::$client ) {
			self::$client = new ML_License_Client_V2(
				array(
					'api_base'   => self::API_BASE,
					'product'    => self::PRODUCT_ID,
					'public_key' => self::PUBLIC_KEY,
					'version'    => ML_OPTIMIZE_PRO_VERSION,
				)
			);
		}
		return self::$client;
	}

	/**
	 * Teste ou licença válidos neste site (conferência local, sem rede).
	 *
	 * @return bool
	 */
	public static function is_active() {
		return self::client()->is_premium();
	}

	/**
	 * Painel travado para gravar (sem teste nem licença).
	 *
	 * @return bool
	 */
	public static function is_locked() {
		if ( null === self::$locked ) {
			self::$locked = ! self::is_active();
		}
		return self::$locked;
	}

	/**
	 * Esquece o resultado guardado (a licença mudou nesta requisição).
	 */
	public static function forget() {
		self::$locked = null;
	}

	/**
	 * URL da aba Licença.
	 *
	 * @param array $args Parâmetros extras.
	 * @return string
	 */
	public static function tab_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/* ------------------------------------------------------------------ ganchos */

	/**
	 * Registra os ganchos. No front-end não registra nada (a licença nunca é lida lá).
	 */
	public static function register() {
		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'boot_admin' ), 1 );
			add_action( 'admin_init', array( __CLASS__, 'maybe_auto_trial' ), 5 );
			add_action( 'admin_notices', array( __CLASS__, 'locked_notice' ) );
			add_action( 'admin_post_ml_optimize_pro_activate_license', array( __CLASS__, 'handle_activate' ) );
			add_action( 'admin_post_ml_optimize_pro_sync_license', array( __CLASS__, 'handle_sync' ) );
			add_action( 'admin_post_ml_optimize_pro_remove_license', array( __CLASS__, 'handle_remove' ) );
			return;
		}
		if ( wp_doing_cron() ) {
			// Só para o evento diário de renovação existir no cron (nada roda no carregamento).
			add_action( 'init', array( __CLASS__, 'client' ) );
		}
	}

	/**
	 * Painel: cria o cliente (agenda a renovação diária) e acompanha mudanças na licença.
	 * Em admin-ajax só cria o cliente quando uma ação do próprio plugin precisar.
	 */
	public static function boot_admin() {
		if ( wp_doing_ajax() ) {
			return;
		}
		self::client();
		self::watch_option();
	}

	/**
	 * Zera o resultado guardado quando a option da licença muda.
	 */
	private static function watch_option() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		$opt  = self::client()->option_name();
		foreach ( array( 'added_option', 'updated_option', 'deleted_option' ) as $hook ) {
			add_action(
				$hook,
				static function ( $name ) use ( $opt ) {
					if ( $name === $opt ) {
						ML_Optimize_Pro_License::forget();
					}
				}
			);
		}
	}

	/**
	 * Primeira visita ao painel: começa o teste grátis sozinho (uma tentativa por site).
	 */
	public static function maybe_auto_trial() {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || get_option( self::AUTO_TRIAL ) ) {
			return;
		}
		update_option( self::AUTO_TRIAL, time(), false );
		$state = self::client()->state();
		if ( empty( $state['active'] ) && 'none' === $state['mode'] && empty( $state['trial_used'] ) ) {
			self::client()->start_trial();
			self::forget();
		}
	}

	/**
	 * Mensagem padrão da trava.
	 *
	 * @return string
	 */
	public static function locked_message() {
		return 'Ative a licença (ou comece o teste grátis) para salvar configurações e usar as ações do painel. O cache e a otimização do site continuam funcionando normalmente.';
	}

	/**
	 * Trava gravações e ações manuais do painel quando não há teste/licença.
	 * Não faz nada quando liberado.
	 *
	 * @param bool $ajax Requisição AJAX (responde JSON 403) ou formulário (volta para a aba Licença).
	 */
	public static function guard_write( $ajax = false ) {
		if ( ! self::is_locked() ) {
			return;
		}
		if ( $ajax ) {
			wp_send_json_error(
				array(
					'message' => self::locked_message(),
					'locked'  => true,
				),
				403
			);
		}
		self::redirect_with_notice( 'error', self::locked_message() );
	}

	/**
	 * Versão para a REST interna do painel.
	 *
	 * @return true|WP_Error
	 */
	public static function rest_guard() {
		if ( ! self::is_locked() ) {
			return true;
		}
		return new WP_Error( 'mlopt_locked', self::locked_message(), array( 'status' => 403 ) );
	}

	/**
	 * Aviso discreto nas telas do plugin quando não há teste/licença.
	 */
	public static function locked_notice() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ML_Optimize_Pro_Admin::MENU_SLUG !== $page || ! current_user_can( 'manage_options' ) || ! self::is_locked() ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>ML Optimize Pro:</strong> %s <a href="%s">%s</a></p></div>',
			esc_html( 'sem licença ativa — as configurações estão só para leitura. O cache e a otimização do site continuam funcionando normalmente.' ),
			esc_url( self::tab_url() ),
			esc_html( 'Ativar licença' )
		);
	}

	/* ------------------------------------------------------------------ tela */

	/**
	 * Estado pronto para a tela.
	 *
	 * @return array
	 */
	public static function summary() {
		$state  = self::client()->state();
		$active = ! empty( $state['active'] );
		$mode   = (string) ( $state['mode'] ?? 'none' );
		$plan   = strtolower( (string) ( $state['plan'] ?? '' ) );
		$days   = $state['days_left'] ?? null;

		$plans      = array(
			'trial'    => 'Teste grátis',
			'monthly'  => 'Mensal',
			'annual'   => 'Anual',
			'yearly'   => 'Anual',
			'lifetime' => 'Vitalícia',
			'manual'   => 'Cortesia',
			'courtesy' => 'Cortesia',
		);
		$plan_label = $plans[ $plan ] ?? ( '' !== $plan ? ucfirst( $plan ) : '—' );

		if ( $active && 'trial' === $mode ) {
			$label   = 'Teste grátis';
			$tone    = 'info';
			$message = null === $days ? 'Teste grátis ativo neste site.' : sprintf( 1 === (int) $days ? 'Teste grátis ativo: falta %d dia.' : 'Teste grátis ativo: faltam %d dias.', (int) $days );
		} elseif ( $active ) {
			$label   = 'Ativa';
			$tone    = 'success';
			$message = 'Licença ativa neste site.';
		} else {
			$label = 'Sem licença';
			$tone  = 'warning';
			if ( 'trial_expired' === ( $state['status'] ?? '' ) ) {
				$message = 'O teste grátis terminou. Ative uma licença para voltar a salvar configurações. O cache e a otimização do site continuam funcionando.';
			} elseif ( '' !== (string) ( $state['message'] ?? '' ) ) {
				$message = (string) $state['message'];
			} else {
				$message = 'Comece o teste grátis de 30 dias ou ative a sua licença.';
			}
		}

		$expires_label = '';
		if ( ! empty( $state['expires_at'] ) ) {
			$ts = strtotime( (string) $state['expires_at'] );
			if ( $ts ) {
				$expires_label = wp_date( get_option( 'date_format', 'd/m/Y' ), $ts );
			}
		}
		if ( ! $active ) {
			$valid_until = '—';
		} elseif ( 'lifetime' === $plan || '' === $expires_label ) {
			$valid_until = 'Sem vencimento';
		} else {
			$valid_until = $expires_label;
		}

		return array(
			'active'          => $active,
			'mode'            => $mode,
			'is_trial'        => $active && 'trial' === $mode,
			'label'           => $label,
			'tone'            => $tone,
			'message'         => $message,
			'plan_label'      => $plan_label,
			'valid_until'     => $valid_until,
			'days_left'       => ( $active && null !== $days && '' !== $expires_label && 'lifetime' !== $plan ) ? (int) $days : null,
			'key_masked'      => (string) ( $state['key_masked'] ?? '' ),
			'can_start_trial' => ! $active && empty( $state['trial_used'] ) && 'license' !== $mode,
			'domain'          => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
		);
	}

	/**
	 * Página Licença (submenu próprio).
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissao negada.', 'ml-optmize-pro' ) );
		}
		echo '<div class="wrap ml-optmize-pro-admin">';
		self::render_tab();
		echo '</div>';
	}

	/**
	 * Conteúdo da Licença (identidade visual do ML Optimize Pro).
	 */
	public static function render_tab() {
		$lic         = self::summary();
		$notice      = isset( $_GET['mlopt_lic_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['mlopt_lic_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$notice_type = isset( $_GET['mlopt_lic_type'] ) ? sanitize_key( wp_unslash( $_GET['mlopt_lic_type'] ) ) : 'success'; // phpcs:ignore WordPress.Security.NonceVerification
		$post_url    = admin_url( 'admin-post.php' );

		ML_Optimize_Pro_Admin_Page::render_header( 'Licença', 'Licença pelo site tools.mlopesdesign.com.br: teste grátis de 30 dias por site, licença anual ou Pacote Agência.' );
		ML_Optimize_Pro_Admin_Page::render_tabs( self::TAB );
		?>
		<div class="mlopt-license">
			<?php if ( '' !== $notice ) : ?>
				<div class="mlopt-notice <?php echo 'error' === $notice_type ? 'mlopt-notice--danger' : 'mlopt-notice--ok'; ?>"><?php echo esc_html( $notice ); ?></div>
			<?php endif; ?>

			<div class="mlopt-summary-grid">
				<div class="mlopt-summary-card">
					<span class="mlopt-summary-label">Situação</span>
					<strong><?php echo esc_html( $lic['label'] ); ?></strong>
					<span class="mlopt-badge mlopt-badge-<?php echo esc_attr( $lic['tone'] ); ?>"><?php echo esc_html( $lic['domain'] ); ?></span>
				</div>
				<div class="mlopt-summary-card">
					<span class="mlopt-summary-label">Plano</span>
					<strong><?php echo esc_html( $lic['plan_label'] ); ?></strong>
					<small><?php echo 'license' === $lic['mode'] && '' !== $lic['key_masked'] ? esc_html( 'Chave ' . $lic['key_masked'] ) : '&nbsp;'; ?></small>
				</div>
				<div class="mlopt-summary-card">
					<span class="mlopt-summary-label">Válida até</span>
					<strong><?php echo esc_html( $lic['valid_until'] ); ?></strong>
					<small><?php echo null !== $lic['days_left'] ? esc_html( sprintf( 1 === $lic['days_left'] ? '%d dia restante' : '%d dias restantes', $lic['days_left'] ) ) : '&nbsp;'; ?></small>
				</div>
			</div>

			<div class="mlopt-grid">
				<div class="mlopt-card">
					<h2>Licença deste site</h2>
					<p><?php echo esc_html( $lic['message'] ); ?></p>

					<?php if ( 'license' === $lic['mode'] && '' !== $lic['key_masked'] ) : ?>
						<ul class="mlopt-stats">
							<li><span>Chave neste site</span><strong><?php echo esc_html( $lic['key_masked'] ); ?></strong></li>
						</ul>
						<div class="mlopt-shortcuts mlopt-license-actions">
							<form method="post" action="<?php echo esc_url( $post_url ); ?>">
								<?php wp_nonce_field( 'ml_optimize_pro_sync_license' ); ?>
								<input type="hidden" name="action" value="ml_optimize_pro_sync_license">
								<input type="hidden" name="mlopt_op" value="check">
								<button type="submit" class="button button-primary"><span class="dashicons dashicons-update"></span> Conferir agora</button>
							</form>
							<form method="post" action="<?php echo esc_url( $post_url ); ?>">
								<?php wp_nonce_field( 'ml_optimize_pro_remove_license' ); ?>
								<input type="hidden" name="action" value="ml_optimize_pro_remove_license">
								<button type="submit" class="button"><span class="dashicons dashicons-no-alt"></span> Remover deste site</button>
							</form>
						</div>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="mlopt-license-form">
							<?php wp_nonce_field( 'ml_optimize_pro_activate_license' ); ?>
							<input type="hidden" name="action" value="ml_optimize_pro_activate_license">
							<div class="mlopt-field">
								<label for="mlopt-license-key" class="mlopt-field-label">Chave da licença</label>
								<input type="text" id="mlopt-license-key" name="license_key" value="" class="regular-text mlopt-input" placeholder="Cole aqui a sua chave" autocomplete="off" spellcheck="false">
								<p class="description">Serve a chave do ML Optimize Pro ou a do Pacote Agência.</p>
							</div>
							<p><button type="submit" class="button button-primary"><span class="dashicons dashicons-yes"></span> Ativar licença</button></p>
						</form>
						<div class="mlopt-shortcuts mlopt-license-actions">
							<?php if ( $lic['can_start_trial'] ) : ?>
								<form method="post" action="<?php echo esc_url( $post_url ); ?>">
									<?php wp_nonce_field( 'ml_optimize_pro_sync_license' ); ?>
									<input type="hidden" name="action" value="ml_optimize_pro_sync_license">
									<input type="hidden" name="mlopt_op" value="trial">
									<button type="submit" class="button"><span class="dashicons dashicons-clock"></span> Começar teste grátis de 30 dias</button>
								</form>
							<?php elseif ( $lic['is_trial'] ) : ?>
								<form method="post" action="<?php echo esc_url( $post_url ); ?>">
									<?php wp_nonce_field( 'ml_optimize_pro_sync_license' ); ?>
									<input type="hidden" name="action" value="ml_optimize_pro_sync_license">
									<input type="hidden" name="mlopt_op" value="check">
									<button type="submit" class="button"><span class="dashicons dashicons-update"></span> Conferir agora</button>
								</form>
							<?php endif; ?>
							<a class="button" href="<?php echo esc_url( self::BUY_URL ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-cart"></span> Comprar licença</a>
							<a class="button" href="<?php echo esc_url( self::ACCOUNT_URL ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-admin-users"></span> Minha conta</a>
						</div>
					<?php endif; ?>
				</div>

				<div class="mlopt-card">
					<h2>Como funciona</h2>
					<table class="widefat striped mlopt-license-table">
						<tbody>
							<tr><td><strong>Teste grátis</strong></td><td>30 dias com tudo liberado, uma vez por site. Reinstalar não reinicia a contagem.</td></tr>
							<tr><td><strong>Licença anual</strong></td><td>Vale em 1 site. Para usar em outro, remova deste site.</td></tr>
							<tr><td><strong>Pacote Agência</strong></td><td>Uma chave para todos os plugins ML em 5, 10 ou 50 sites.</td></tr>
							<tr><td><strong>Sem licença</strong></td><td>O cache e a otimização continuam funcionando no site, do jeito que estão. Só o painel fica bloqueado para salvar configurações e usar as ações (purgar cache, limpar banco etc.).</td></tr>
						</tbody>
					</table>
					<p class="description">A licença é conferida uma vez por dia, em segundo plano — nunca durante a visita de alguém ao site. Sem internet, o site continua liberado por até 7 dias.</p>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ ações */

	/**
	 * Ativa a chave informada.
	 */
	public static function handle_activate() {
		self::guard_admin_request( 'ml_optimize_pro_activate_license' );
		$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$result = self::client()->activate( $key );
		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( 'error', $result->get_error_message() );
		}
		self::redirect_with_notice( 'success', 'Licença ativada neste site.' );
	}

	/**
	 * Começa o teste grátis ou confere a licença agora.
	 */
	public static function handle_sync() {
		self::guard_admin_request( 'ml_optimize_pro_sync_license' );
		$op     = isset( $_POST['mlopt_op'] ) ? sanitize_key( wp_unslash( $_POST['mlopt_op'] ) ) : 'check';
		$result = 'trial' === $op ? self::client()->start_trial() : self::client()->refresh();
		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( 'error', $result->get_error_message() );
		}
		self::redirect_with_notice( 'success', 'trial' === $op ? 'Teste grátis de 30 dias ativo neste site.' : 'Licença conferida.' );
	}

	/**
	 * Remove a licença deste site (libera a chave para outro site).
	 */
	public static function handle_remove() {
		self::guard_admin_request( 'ml_optimize_pro_remove_license' );
		self::client()->deactivate();
		self::redirect_with_notice( 'success', 'Licença removida deste site. A chave pode ser usada em outro site.' );
	}

	/**
	 * Permissão + nonce das ações da aba Licença.
	 *
	 * @param string $nonce_action Ação do nonce.
	 */
	private static function guard_admin_request( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissao negada.', 'ml-optmize-pro' ) );
		}
		check_admin_referer( $nonce_action );
	}

	/**
	 * Volta para a aba Licença com um aviso.
	 *
	 * @param string $type    success|error.
	 * @param string $message Mensagem.
	 */
	public static function redirect_with_notice( $type, $message ) {
		wp_safe_redirect(
			self::tab_url(
				array(
					'mlopt_lic_type'   => rawurlencode( $type ),
					'mlopt_lic_notice' => rawurlencode( $message ),
				)
			)
		);
		exit;
	}
}
