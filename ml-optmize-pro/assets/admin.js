/* ==========================================================================
   ML Optimize Pro — Admin JS
   AJAX save (modulos inteiros), purge cache, db cleanup, script rules
   ========================================================================== */
(function($){
	'use strict';
	if (typeof mlopt === 'undefined') return;
	const strings = mlopt.strings || {};

	// Helper: AJAX
	function mloptAjax(action, data) {
		return $.post(mlopt.ajaxUrl, Object.assign({
			action: action,
			nonce: mlopt.nonce
		}, data || {}));
	}

	// Show save status
	function setStatus($el, msg, type) {
		$el.removeClass('is-success is-error').addClass('is-visible');
		if (type) $el.addClass('is-' + type);
		$el.text(msg);
		setTimeout(function(){
			$el.removeClass('is-visible is-success is-error');
		}, 2400);
	}

	// === SAVE MODULE ===
	$(document).on('click', '.mlopt-btn-save', function(){
		const $btn = $(this);
		const $form = $btn.closest('form');
		const module = $btn.data('save') || $form.data('module');
		const $status = $form.find('.mlopt-save-status');
		$btn.prop('disabled', true);
		setStatus($status, strings.saving || 'Salvando...');

		// Coleta todos os fields do form.
		const payload = {};
		$form.find('input, textarea, select').each(function(){
			const $f = $(this);
			const name = $f.attr('name');
			if (!name) return;
			if ($f.is(':checkbox')) {
				payload[name] = $f.is(':checked') ? 1 : 0;
			} else if ($f.is(':radio')) {
				if ($f.is(':checked')) payload[name] = $f.val();
			} else {
				payload[name] = $f.val();
			}
		});

		// Script rules: serializa regras em rules_json.
		if (module === 'script_manager') {
			const rules = collectRules();
			payload['rules_json'] = JSON.stringify(rules);
		}

		mloptAjax('ml_optimize_pro_save_module', { module: module, payload: JSON.stringify(payload) })
			.done(function(r){
				if (r && r.success) {
					setStatus($status, strings.saved || 'Salvo', 'success');
				} else {
					setStatus($status, (r && r.data && r.data.message) || strings.error || 'Erro', 'error');
				}
			})
			.fail(function(){
				setStatus($status, strings.error || 'Erro', 'error');
			})
			.always(function(){
				$btn.prop('disabled', false);
			});
	});

	// === QUICK ACTIONS ===
	$(document).on('click', '.mlopt-btn[data-action]', function(){
		const $btn = $(this);
		const action = $btn.data('action');
		$btn.prop('disabled', true);
		switch (action) {
			case 'purge-cache':
				if (!confirm(strings.confirmPurge)) { $btn.prop('disabled', false); return; }
				mloptAjax('ml_optimize_pro_purge_cache').always(function(r){
					$btn.prop('disabled', false);
					if (r && r.success) {
						$btn.text('Purgado: ' + (r.data && r.data.purged ? r.data.purged : 0) + ' arquivos');
					}
				});
				break;
			case 'run-db':
				mloptAjax('ml_optimize_pro_run_db_cleanup').always(function(r){
					$btn.prop('disabled', false);
					if (r && r.success && r.data && r.data.stats) {
						const s = r.data.stats;
						$btn.text('Limpos: ' + (s.revisions + s.autodrafts + s.trash + s.spam + s.transients));
					}
				});
				break;
			case 'check-update':
				mloptAjax('ml_optimize_pro_check_update').always(function(r){
					$btn.prop('disabled', false);
					if (r && r.success) {
						$btn.text('Update checado (versao: ' + (r.data.release && r.data.release.tag_name) + ')');
					} else {
						$btn.text('Sem update disponivel');
					}
				});
				break;
			case 'reset-settings':
				if (!confirm(strings.confirmReset)) { $btn.prop('disabled', false); return; }
				mloptAjax('ml_optimize_pro_reset_settings').always(function(r){
					$btn.prop('disabled', false);
					if (r && r.success) location.reload();
				});
				break;
			case 'export-settings':
				mloptAjax('ml_optimize_pro_export_settings').always(function(r){
					$btn.prop('disabled', false);
					if (r && r.success && r.data && r.data.json) {
						const blob = new Blob([r.data.json], { type: 'application/json' });
						const url = URL.createObjectURL(blob);
						const a = document.createElement('a');
						a.href = url;
						a.download = 'ml-optmize-pro-settings.json';
						a.click();
						URL.revokeObjectURL(url);
					}
				});
				break;
			case 'import-settings':
				const inp = document.createElement('input');
				inp.type = 'file';
				inp.accept = 'application/json';
				inp.onchange = function(){
					const file = inp.files[0];
					if (!file) { $btn.prop('disabled', false); return; }
					const rdr = new FileReader();
					rdr.onload = function(){
						mloptAjax('ml_optimize_pro_import_settings', { json: rdr.result })
							.always(function(r2){
								$btn.prop('disabled', false);
								if (r2 && r2.success) location.reload();
							});
					};
					rdr.readAsText(file);
				};
				inp.click();
				break;
			case 'clear-logs':
				mloptAjax('ml_optimize_pro_clear_logs').always(function(r){
					$btn.prop('disabled', false);
					if (r && r.success) location.reload();
				});
				break;
			case 'apply-browser-cache':
				mloptAjax('ml_optimize_pro_apply_browser_cache').always(function(r){
					$btn.prop('disabled', false);
					if (r && r.success) $btn.text('Regras aplicadas');
				});
				break;
		}
	});

	// === SCRIPT MANAGER RULES ===
	const $rules = $('#mlopt-rules');
	function ruleTemplate(idx, rule) {
		rule = rule || { scope: 'everywhere', handles: '', type: 'all', device: 'all', note: '' };
		return '' +
		'<div class="mlopt-rule" data-idx="' + idx + '">' +
			'<select class="mlopt-rule-scope" data-k="scope">' +
				'<option value="everywhere">Em toda parte</option>' +
				'<option value="current">Pagina/post atual</option>' +
				'<option value="post_type">Por post type</option>' +
				'<option value="archive">Archives</option>' +
				'<option value="front_page">Home</option>' +
				'<option value="blog">Blog page</option>' +
				'<option value="search">Search</option>' +
				'<option value="404">404</option>' +
			'</select>' +
			'<input type="text" class="mlopt-rule-handles" placeholder="handle1, handle2, /regex/" data-k="handles" />' +
			'<select class="mlopt-rule-type" data-k="type">' +
				'<option value="all">Script + CSS</option>' +
				'<option value="script">Apenas script</option>' +
				'<option value="style">Apenas CSS</option>' +
			'</select>' +
			'<select class="mlopt-rule-device" data-k="device">' +
				'<option value="all">Todos devices</option>' +
				'<option value="desktop">Apenas desktop</option>' +
				'<option value="mobile">Apenas mobile</option>' +
			'</select>' +
			'<input type="text" class="mlopt-rule-full" placeholder="Nota (opcional)" data-k="note" />' +
			'<div class="mlopt-rule-actions">' +
				'<button type="button" class="button mlopt-rule-del">Remover</button>' +
			'</div>' +
		'</div>';
	}
	function collectRules() {
		const out = [];
		$rules.children('.mlopt-rule').each(function(){
			const $r = $(this);
			const rule = {};
			$r.find('[data-k]').each(function(){
				const k = $(this).data('k');
				let v = $(this).val();
				if (k === 'handles' && typeof v === 'string') {
					v = v.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
				}
				rule[k] = v;
			});
			if (rule.handles && rule.handles.length) out.push(rule);
		});
		return out;
	}
	$(document).on('click', '#mlopt-add-rule', function(){
		const idx = $rules.children('.mlopt-rule').length;
		$rules.append(ruleTemplate(idx, null));
	});
	$(document).on('click', '.mlopt-rule-del', function(){
		if (!confirm(strings.confirmDelete)) return;
		$(this).closest('.mlopt-rule').remove();
	});

	// Init rules from window.mloptRules
	if (window.mloptRules && Array.isArray(window.mloptRules)) {
		window.mloptRules.forEach(function(r, i){
			$rules.append(ruleTemplate(i, r));
			const $r = $rules.children().last();
			$r.find('[data-k="scope"]').val(r.scope || 'everywhere');
			$r.find('[data-k="handles"]').val((r.handles || []).join(', '));
			$r.find('[data-k="type"]').val(r.type || 'all');
			$r.find('[data-k="device"]').val(r.device || 'all');
			$r.find('[data-k="note"]').val(r.note || '');
		});
	}
})(jQuery);
