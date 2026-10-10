/**
 * Lingua Visual — modo visual: clickeá un texto y traducilo en el acto.
 */
(function () {
	'use strict';

	if (typeof lgvVisual === 'undefined') {
		return;
	}

	var overlay = null;
	var hintBar = null;

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) {
			n.className = cls;
		}
		if (text) {
			n.textContent = text;
		}
		return n;
	}

	function showHint() {
		hintBar = el('div', 'lgv-hint-bar', lgvVisual.i18n.hint);
		document.body.appendChild(hintBar);
	}

	function hideHint() {
		if (hintBar && hintBar.parentNode) {
			hintBar.parentNode.removeChild(hintBar);
		}
		hintBar = null;
	}

	/**
	 * Texto a traducir: si hay selección, usa la selección; si no, el texto
	 * del elemento clickeado (limitado a 300 chars para no tragar contenedores).
	 */
	function extractText(target) {
		var sel = window.getSelection ? window.getSelection().toString().trim() : '';
		if (sel) {
			return sel.substring(0, 2000);
		}
		var text = (target.innerText || target.textContent || '').trim().replace(/\s+/g, ' ');
		return text.substring(0, 300);
	}

	function openModal(original, target) {
		closeModal();

		overlay = el('div', 'lgv-overlay');

		var modal = el('div', 'lgv-modal');
		modal.appendChild(el('h3', 'lgv-modal-title', lgvVisual.i18n.title));

		var labOrig = el('label', 'lgv-label', lgvVisual.i18n.original);
		var inpOrig = document.createElement('textarea');
		inpOrig.className = 'lgv-input';
		inpOrig.rows = 3;
		inpOrig.value = original;
		modal.appendChild(labOrig);
		modal.appendChild(inpOrig);

		var labLang = el('label', 'lgv-label', lgvVisual.i18n.lang);
		var selLang = document.createElement('select');
		selLang.className = 'lgv-input';
		lgvVisual.langs.forEach(function (l) {
			var opt = document.createElement('option');
			opt.value = l.code;
			opt.textContent = l.label;
			if (l.code === lgvVisual.currentLang) {
				opt.selected = true;
			}
			selLang.appendChild(opt);
		});
		modal.appendChild(labLang);
		modal.appendChild(selLang);

		var labTrans = el('label', 'lgv-label', lgvVisual.i18n.translation);
		var inpTrans = document.createElement('textarea');
		inpTrans.className = 'lgv-input';
		inpTrans.rows = 3;
		inpTrans.placeholder = '…';
		modal.appendChild(labTrans);
		modal.appendChild(inpTrans);

		var msg = el('p', 'lgv-msg');
		modal.appendChild(msg);

		var actions = el('div', 'lgv-actions');
		var btnSave = el('button', 'lgv-btn lgv-btn-primary', lgvVisual.i18n.save);
		btnSave.type = 'button';
		var btnCancel = el('button', 'lgv-btn', lgvVisual.i18n.cancel);
		btnCancel.type = 'button';
		actions.appendChild(btnCancel);
		actions.appendChild(btnSave);
		modal.appendChild(actions);

		overlay.appendChild(modal);
		document.body.appendChild(overlay);

		// Sugerencia automática opcional (hook lgv_auto_translate del lado PHP).
		fetchSuggestion(inpOrig.value.trim(), selLang.value, inpTrans);

		selLang.addEventListener('change', function () {
			fetchSuggestion(inpOrig.value.trim(), selLang.value, inpTrans);
		});

		btnCancel.addEventListener('click', closeModal);
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) {
				closeModal();
			}
		});

		btnSave.addEventListener('click', function () {
			var payload = new URLSearchParams();
			payload.append('action', 'lgv_save_visual_translation');
			payload.append('nonce', lgvVisual.nonce);
			payload.append('original', inpOrig.value.trim());
			payload.append('lang', selLang.value);
			payload.append('translation', inpTrans.value.trim());
			payload.append('context', window.location.href);

			btnSave.disabled = true;
			msg.className = 'lgv-msg';
			msg.textContent = '…';

			fetch(lgvVisual.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: payload.toString()
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					btnSave.disabled = false;
					if (data && data.success) {
						msg.className = 'lgv-msg lgv-ok';
						msg.textContent = lgvVisual.i18n.saved;
						if (target && target.classList) {
							target.classList.add('lgv-translated');
						}
						setTimeout(closeModal, 1200);
					} else {
						msg.className = 'lgv-msg lgv-err';
						msg.textContent = (data && data.data && data.data.message) || lgvVisual.i18n.error;
					}
				})
				.catch(function () {
					btnSave.disabled = false;
					msg.className = 'lgv-msg lgv-err';
					msg.textContent = lgvVisual.i18n.error;
				});
		});

		inpTrans.focus();
	}

	function fetchSuggestion(original, lang, input) {
		if (!original || !lang) {
			return;
		}
		var payload = new URLSearchParams();
		payload.append('action', 'lgv_visual_suggest');
		payload.append('nonce', lgvVisual.nonce);
		payload.append('original', original);
		payload.append('lang', lang);
		fetch(lgvVisual.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: payload.toString()
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data && data.success && data.data && data.data.suggestion && !input.value) {
					input.value = data.data.suggestion;
				}
			})
			.catch(function () { /* sugerencia opcional: silencio */ });
	}

	function closeModal() {
		if (overlay && overlay.parentNode) {
			overlay.parentNode.removeChild(overlay);
		}
		overlay = null;
	}

	function onMouseOver(e) {
		var t = e.target;
		if (!t || !t.tagName || t.closest('.lgv-overlay, #wpadminbar, .lgv-hint-bar, script, style')) {
			return;
		}
		t.classList.add('lgv-hover');
	}

	function onMouseOut(e) {
		var t = e.target;
		if (t && t.classList) {
			t.classList.remove('lgv-hover');
		}
	}

	function onClick(e) {
		var t = e.target;
		if (!t || t.closest('.lgv-overlay, #wpadminbar, .lgv-hint-bar')) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var text = extractText(t);
		if (text.length < 2) {
			return;
		}
		openModal(text, t);
	}

	document.addEventListener('mouseover', onMouseOver, true);
	document.addEventListener('mouseout', onMouseOut, true);
	document.addEventListener('click', onClick, true);
	document.body.classList.add('lgv-visual-active');
	showHint();

	// Escape cierra el modal.
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			closeModal();
		}
	});
})();
