=== ML Optimize Pro ===
Contributors: mlopesdesign
Tags: performance, cache, optimization, core web vitals, speed, lazy load, cdn, script manager, bloat remover, lcp, inp, cls
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Suite premium de otimizacao WordPress: cache, minify, defer/delay JS, remove unused CSS, lazy load, lazy render, Script Manager, Bloat Remover, Google Fonts local, preload, DB cleanup, CDN, Speculation Rules e Performance Hub.

== Description ==

ML Optimize Pro e a suite premium de otimizacao WordPress feita pela ML Lopes Design. Concentra as melhores praticas do mercado (WP Rocket, Perfmatters, FlyingPress, LiteSpeed, WP-Optimize) em um unico plugin nativo, sem dependencias externas, com auto-update via GitHub e Performance Hub com score CWV.

**Cache premium**
* Page cache em disco com mobile separado
* Cache preloading via sitemap
* Cache lifespan configuravel com purge automatico
* Browser cache (expires headers + ETags)
* Gzip / Brotli detection

**File Optimization (Core Web Vitals)**
* Minify e combine CSS / JS / HTML
* Remove Unused CSS (critical CSS async)
* Defer JavaScript (elimina render-blocking)
* Delay JavaScript execution (aguarda interacao do usuario)
* Load CSS Asynchronously

**Lazy Load & Lazy Render**
* Lazy load nativo de imagens, iframes, videos
* Lazy load de CSS background images
* Substituicao de YouTube iframe por preview image (lite-youtube-embed)
* Lazy render por seletores CSS (footer, sidebar, comentarios, sections)

**Script Manager (estilo Perfmatters)**
* Desabilita scripts e CSS por post / page / post type / arquivo
* Por dispositivo (desktop / mobile) e estado do usuario (logado / deslogado)
* Suporte a Regex
* MU mode para controle total (queries, hooks, inline)
* Excecoes por URL

**Bloat Remover**
* Desabilita emojis, embeds, dashicons, jQuery Migrate, XML-RPC
* Remove query strings, versao do WP, RSD, shortlink, RSS
* Desabilita WooCommerce bloat em paginas nao-shop
* Desabilita Contact Form 7 / Gravity Forms em paginas sem form
* Desabilita Google Maps em todo o site, ativa por excecao
* Heartbeat control (frontend / backend / editor)
* Limita revisoes, autosave, blocos Gutenberg

**Fonts Optimization**
* Self-host Google Fonts (download local)
* Combine e preload de fontes
* font-display: swap automatico
* System fonts first
* Fallback fonts com size-adjust

**Preload & Resource Hints**
* Preload de recursos criticos
* DNS prefetch e preconnect de dominios externos
* Speculation Rules API (link preload nativo no hover)

**Image Optimization**
* Add missing width / height (anti-CLS)
* Lazy load nativo (loading="lazy")
* fetchpriority="high" para imagem LCP
* AVIF / WebP detection

**Database Optimization**
* Limpa revisoes, autodrafts, trashed posts, spam comments, transients expirados
* Otimiza tabelas MyISAM / InnoDB
* Schedule automatico (diario / semanal)

**CDN & Compatibility**
* Rewrite de URLs estaticas para CDN
* Exclude paths configuraveis
* Compatibilidade com Cloudflare, BunnyCDN, QUIC.cloud

**Performance Hub**
* Dashboard com score CWV estimado
* Contadores de scripts / CSS / imagens / fonts
* Teste rapido de PageSpeed local
* Export / Import de configuracoes

**Auto-update via GitHub**
* Checagem diaria via cron
* Update direto pelo painel WP
* Instalacao por cima sem perder configuracoes
* Cache de 6h para evitar rate limit

**Seguranca**
* Capability checks em todas as telas
* Nonces em todos os formularios e AJAX
* Sanitizacao de entrada + escape de saida
* Uninstall.php limpa todas as opcoes, transients, cache e MU
* Sem telemetria, sem terceiros, sem credenciais

REST interno: /wp-json/ml-optimize-pro/v1/status
Frontend JS: enfileirado condicionalmente (so carrega se houver otimizacao ativa)
Compatibilidade: WordPress 6.0+ / PHP 7.4+ / Servidor: Apache, Nginx, LiteSpeed

== Installation ==

1. Faca upload do ZIP `ml-optmize-pro-v1.0.0.zip` em Plugins > Adicionar novo > Upload.
2. Ative o plugin.
3. Va em "ML Optimize Pro" no menu lateral.
4. Ative o que quiser — cache ja vem ligado por padrao.
5. Auto-update via GitHub funciona automaticamente.

== Frequently Asked Questions ==

= O plugin substitui WP Rocket e Perfmatters? =
Sim, para a grande maioria dos sites. ML Optimize Pro combina cache, otimizacao de arquivos, script manager, bloat remover e dashboard de performance em um unico plugin premium.

= Funciona com WooCommerce? =
Sim. Ha tratamento de carrinho, checkout e minha conta para nao quebrar o frontend, e limpeza de cart fragments.

= Funciona com page builders (Elementor, Divi, Bricks)? =
Sim. Modulos de Remove Unused CSS e Script Manager tem exclusion lists para os principais builders.

= Tem auto-update? =
Sim, via GitHub Releases. O plugin consulta o repositorio oficial uma vez por dia.

= O cache e compat com Cloudflare APO? =
Sim. A combinacao de cache local + Cloudflare APO entrega TTFB baixo.

= Como desativo uma otimizacao? =
Em ML Optimize Pro > Modulos, desligue o switch. Todas as otimizacoes sao granulares.

== Screenshots ==

1. Performance Hub com score CWV
2. Modulos de cache e file optimization
3. Script Manager estilo Perfmatters
4. Bloat Remover
5. Fontes locais Google Fonts
6. Database cleanup

== Changelog ==





= 1.0.7 =
* IDENTIDADE VISUAL OFICIAL: paleta trocada de verde #0d7a3a (eu inventei) para TEAL #155e6f (oficial do ml-plugin-base v1.1.1). Cor da marca, brand-dark, brand-soft, sombras, focus ring, gradiente do hero-mark e fundo da pagina admin (linear-gradient) agora batem 1:1 com o plugin base.
* LOGO OFICIAL: adicionado `assets/images/logo-wordpress.png` (mesmo pin/marcador teal usado em TODOS os plugins ML). O hero agora mostra a logo real no lugar do placeholder `<span>ML</span>`. Caminho resolvido via `ML_OPTIMIZE_PRO_URL . 'assets/images/logo-wordpress.png'`.
* EYEBROW OFICIAL: trocado de "ML Optimize Pro" para "ML Lopes Design · Optimize Pro" (padrao do ml-plugin-base v1.1.1, igual "ML Lopes Design · Plugin Base").
* CSS do hero-mark: removido o `<span>ML</span>` styling, adicionado `overflow: hidden` + regras para `<img>` (display block, max-width 100%, max-height 88px, object-fit contain) - mesma logica do `.mlpb-hero-mark img` no plugin base.
* SEM mudanca de slug, prefixo, classes, JS, hooks, options, comportamento, AJAX ou HTML estrutural (so o conteudo do hero-mark/eyebrow e a paleta CSS).
* Backward compatible com 1.0.6.

= 1.0.6 =
* HOTFIX critico: adicionadas 3 classes CSS que faltavam no v1.0.5 e QUEBRARAM o layout. O HTML renderizado usava `.mlopt-grid` (cards do dashboard), `.mlopt-form` (forms das tabs) e `.mlopt-log-msg` (linhas de log), mas o CSS da v1.0.5 só tinha `.mlopt-cards-grid` (eu renomeei sem atualizar o HTML). Resultado: dashboard empilhado em vez de grid, forms sem estilo, e log bagunçado. v1.0.6 adiciona as 3 classes (`.mlopt-grid` compartilha CSS com `.mlopt-cards-grid`; `.mlopt-form` e `.mlopt-log-msg` adicionados diretamente).
* SEM mudanca de identidade visual, JS, hooks, classes, options ou HTML estrutural.
* SEM mudanca de slug, prefixo ou cor da marca.
* Backward compatible com 1.0.5 (so adiciona 3 regras CSS).

**IMPORTANTE pro user:** apos atualizar, faca HARD REFRESH no browser (Ctrl+F5) ou abra em janela anonima. O `?ver=1.0.6` ja quebra o cache de 1 ano do browser, mas se mesmo assim o layout nao aplicar, verifique o plugin de cache do WP (LiteSpeed, W3, WP Rocket) e limpe o cache de CSS.

= 1.0.5 =
* REVERT: identidade visual reescrita pra replicar 1:1 o padrao do plugin base `ml-app-base-core` (cores WP nativas + verde da marca `#0d7a3a`, sem glassmorphism, sem gradient roxo-ciano, sem SVG score ring de 160px, com hero `mlopt-admin-hero` + tabs `mlopt-admin-tabs` no formato `mlcatp-admin-tabs` + cards com border-radius 18px + botoes pill da cor da marca).
* REVERT: `render_header()` reescrito (substitui SVG `<linearGradient roxo-ciano>` por `<div class="mlopt-admin-hero-mark"><span>ML</span></div>`, igual `mlab-admin-hero-mark`).
* REVERT: `render_tabs()` renomeado de `mlopt-tabs`/`mlopt-tab` para `mlopt-admin-tabs`/`mlopt-admin-tab` (padrao `mlcatp-admin-tabs`).
* REVERT: score ring SVG removido do dashboard, substituido por display simples padrao ML (`mlopt-score-display` + `mlopt-score-value-big`, igual `mlcatp-summary-card`).
* REVERT: `wp_add_inline_style( $handle, critical_inline_css() )` removido do `enqueue_assets` (nao precisa mais com o CSS externo replicado 1:1 do base, sem cache de browser segurando CSS antigo).
* REVERT: metodo `critical_inline_css()` removido do admin.php.
* Mantido: enfileiramento sempre em `admin_enqueue_scripts` (sem filtro strpos), nome do objeto JS `mlopt` (consistente com `wp_localize_script`).

= 1.0.4 =
* HOTFIX: adicionado CSS inline critico via `wp_add_inline_style` no `enqueue_assets()`. Renderiza no `<head>` DEPOIS do `<link>` do arquivo externo, com `!important` e alta especificidade, sobrepondo QUALQUER cache (browser, plugin de cache WP, CDN). Garante a identidade visual ML mesmo se o arquivo CSS externo falhar ao carregar.
* HOTFIX: regras criticas de container (gradient), hero (border-left verde 6px), tabs (pill), cards (shadow), score ring (160px), botoes (pill verde) agora duplicadas inline - defesa em profundidade contra cache de browser que prende CSS antigo por 1 ano.

= 1.0.3 =
* HOTFIX: removido filtro strpos no `enqueue_assets` que impedia o CSS admin de ser carregado. Agora o CSS e o JS sao enfileirados em qualquer pagina admin relacionada.
* HOTFIX: corrigido nome do objeto JS no `wp_localize_script` (era `mlopt`, o JS procurava por `MLOptPro` — quebrava todos os handlers AJAX). Agora consistente.

= 1.0.2 =
* IDENTIDADE VISUAL: admin UI reescrito seguindo o padrao visual dos plugins ML (ml-app-base-core, ML Catalogo Pro). Cor da marca verde escuro (#0d7a3a) com brand-soft #e7f4ec, hero com border-left 6px, cards com border-radius 18px e box-shadow suave, tabs pill, botoes pill. Removido glassmorphism e gradient roxo-ciano que nao combinavam com a identidade ML. Prefixo CSS renomeado de `mloptpro-` para `mlopt-` (padrao dos outros ML).

= 1.0.1 =
* HOTFIX: corrigido erro fatal na ativacao em PHP 8.x. O Activator usava `$this->_php_string` dentro de metodo estatico (`mu_loader_template`), o que dispara "Using $this when not in object context" em PHP 8.0+. Trocado por `self::_php_string` e adicionado try/catch granular em cada etapa da ativacao para que uma falha nao quebre o painel.
* HOTFIX: MU loader agora e sempre reescrito na ativacao para curar instalacoes que tenham gravado MU file com erro em tentativas anteriores.

= 1.0.0 =
* Lancamento inicial: cache de pagina, minify CSS/JS, defer/delay JS, remove unused CSS, lazy load, lazy render, script manager, bloat remover, self-host Google Fonts, preload, Speculation Rules, DB cleanup, heartbeat control, CDN rewrite, Performance Hub, auto-update via GitHub.

== Upgrade Notice ==

= 1.0.2 =
Atualizacao visual. Toda a UI admin foi reescrita para seguir a identidade visual ML. Sem mudanca de comportamento — atualize por cima sem perder configuracoes.

= 1.0.1 =
Hotfix critico. Se a 1.0.0 deu erro fatal na ativacao, faca upload deste ZIP por cima — o instalador vai normalizar o MU file e ativar normalmente.

= 1.0.0 =
Versao inicial. Cache ja vem ligado por padrao, demais modulos sao opcionais com 1 clique.
