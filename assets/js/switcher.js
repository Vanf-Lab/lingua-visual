/**
 * Lingua Visual — Switcher Studio (frontend).
 * Dropdown (click/hover), modal, buscador, teclado y ARIA.
 */
(function () {
	'use strict';

	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

	function closeAll(except) {
		$all('[data-lgv]').forEach(function (root) {
			if (root === except) return;
			closeRoot(root);
		});
	}

	function closeRoot(root) {
		root.setAttribute('data-open', '0');
		var menu = $('.lgv-sw-menu', root);
		if (menu) {
			menu.hidden = true;
			var t = $('.lgv-sw-toggle', root);
			if (t) t.setAttribute('aria-expanded', 'false');
		}
		var ov = $('.lgv-sw-overlay', root);
		if (ov && !ov.hidden) {
			ov.hidden = true;
			var p = $('.lgv-sw-pillbtn', root);
			if (p) {
				p.setAttribute('aria-expanded', 'false');
				p.focus();
			}
		}
	}

	function openMenu(root) {
		closeAll(root);
		root.setAttribute('data-open', '1');
		var menu = $('.lgv-sw-menu', root);
		if (!menu) return;
		menu.hidden = false;
		var t = $('.lgv-sw-toggle', root);
		if (t) t.setAttribute('aria-expanded', 'true');
		var first = $('.lgv-sw-item', menu);
		if (first) first.focus();
	}

	function openModal(root) {
		closeAll(root);
		var ov = $('.lgv-sw-overlay', root);
		if (!ov) return;
		ov.hidden = false;
		var p = $('.lgv-sw-pillbtn', root);
		if (p) p.setAttribute('aria-expanded', 'true');
		root.setAttribute('data-open', '1');
		var target = $('.lgv-sw-search input', ov) || $('.lgv-sw-close', ov);
		if (target) target.focus();
	}

	function items(root) {
		var scope = $('.lgv-sw-menu-items', root) || $('.lgv-sw-modal-list', root);
		if (!scope) return [];
		return $all('.lgv-sw-item[href]', scope).filter(function (el) {
			return el.style.display !== 'none';
		});
	}

	function focusNext(root, dir) {
		var list = items(root);
		if (!list.length) return;
		var i = list.indexOf(document.activeElement);
		i = i < 0 ? (dir > 0 ? 0 : list.length - 1) : (i + dir + list.length) % list.length;
		list[i].focus();
	}

	function bindSearch(root) {
		var input = $('[data-lgv-search]', root);
		if (!input || input.dataset.bound) return;
		input.dataset.bound = '1';
		input.addEventListener('input', function () {
			var q = input.value.toLowerCase().trim();
			var scope = input.closest('.lgv-sw-menu, .lgv-sw-modal');
			$all('.lgv-sw-item', scope).forEach(function (el) {
				var hay = (el.getAttribute('data-search') || '').toLowerCase();
				el.classList.toggle('lgv-hidden', q !== '' && hay.indexOf(q) === -1);
			});
		});
		input.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown') { e.preventDefault(); focusNext(root, 1); }
			if (e.key === 'Escape') { e.stopPropagation(); closeRoot(root); }
		});
	}

	function initRoot(root) {
		if (root.dataset.lgvInit) return;
		root.dataset.lgvInit = '1';
		var mode = root.getAttribute('data-lgv-mode');
		var trigger = root.getAttribute('data-lgv-trigger');
		var closeOutside = root.getAttribute('data-lgv-close-outside') === '1';

		var toggle = $('.lgv-sw-toggle', root);
		if (toggle && (mode === 'dropdown')) {
			if (trigger === 'hover') {
				root.addEventListener('mouseenter', function () { openMenu(root); });
				root.addEventListener('mouseleave', function () { closeRoot(root); });
			} else {
				toggle.addEventListener('click', function (e) {
					e.stopPropagation();
					if (root.getAttribute('data-open') === '1') closeRoot(root);
					else openMenu(root);
				});
			}
			root.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') { closeRoot(root); toggle.focus(); }
				if (e.key === 'ArrowDown') { e.preventDefault(); openMenu(root); }
			});
		}

		var pill = $('.lgv-sw-pillbtn', root);
		if (pill) {
			pill.addEventListener('click', function (e) {
				e.stopPropagation();
				var ov = $('.lgv-sw-overlay', root);
				if (ov && ov.hidden) openModal(root); else closeRoot(root);
			});
		}

		var closer = $('.lgv-sw-close', root);
		if (closer) closer.addEventListener('click', function () { closeRoot(root); });

		var overlay = $('.lgv-sw-overlay', root);
		if (overlay) {
			overlay.addEventListener('click', function (e) {
				if (e.target === overlay) closeRoot(root);
			});
			overlay.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') { e.stopPropagation(); closeRoot(root); }
				if (e.key === 'ArrowDown') { e.preventDefault(); focusNext(root, 1); }
				if (e.key === 'ArrowUp') { e.preventDefault(); focusNext(root, -1); }
			});
		}

		var menu = $('.lgv-sw-menu', root);
		if (menu) {
			menu.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowDown') { e.preventDefault(); focusNext(root, 1); }
				if (e.key === 'ArrowUp') { e.preventDefault(); focusNext(root, -1); }
				if (e.key === 'Escape') { e.stopPropagation(); closeRoot(root); if (toggle) toggle.focus(); }
			});
		}

		bindSearch(root);

		if (closeOutside) {
			document.addEventListener('click', function (e) {
				if (!root.contains(e.target)) closeRoot(root);
			});
		}
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && root.getAttribute('data-open') === '1') closeRoot(root);
		});
	}

	function init(ctx) {
		$all('[data-lgv]', ctx || document).forEach(initRoot);
		bindTips();
	}

	/* ===== Tooltip con el nombre del idioma (data-lgv-tip) =====
	 * Anclado al body para que no lo recorte el overflow del dropdown/modal. */

	var tipEl = null;
	var tipTarget = null;
	var tipsBound = false;

	function showTip(target) {
		var text = target.getAttribute('data-lgv-tip');
		if (!text) return;
		hideTip();
		tipEl = document.createElement('div');
		tipEl.className = 'lgv-tip';
		tipEl.setAttribute('role', 'tooltip');
		tipEl.textContent = text;
		tipEl.style.visibility = 'hidden';
		document.body.appendChild(tipEl);
		var r = target.getBoundingClientRect();
		var tw = tipEl.offsetWidth;
		var th = tipEl.offsetHeight;
		var x = r.left + (r.width / 2) - (tw / 2) + window.scrollX;
		x = Math.max(8, Math.min(x, window.innerWidth - tw - 8));
		var y = r.top - th - 8 + window.scrollY;
		if (y < window.scrollY + 4) {
			y = r.bottom + 8 + window.scrollY; // sin lugar arriba: abajo
		}
		tipEl.style.left = Math.round(x) + 'px';
		tipEl.style.top = Math.round(y) + 'px';
		tipEl.style.visibility = 'visible';
	}

	function hideTip() {
		if (tipEl && tipEl.parentNode) {
			tipEl.parentNode.removeChild(tipEl);
		}
		tipEl = null;
	}

	function bindTips() {
		if (tipsBound) return;
		tipsBound = true;
		document.addEventListener('mouseover', function (e) {
			var t = e.target && e.target.closest ? e.target.closest('[data-lgv-tip]') : null;
			if (t === tipTarget) return;
			tipTarget = t;
			if (t) showTip(t); else hideTip();
		});
		document.addEventListener('mouseout', function (e) {
			if (tipTarget && !(e.relatedTarget && tipTarget.contains(e.relatedTarget))) {
				tipTarget = null;
				hideTip();
			}
		});
		document.addEventListener('focusin', function (e) {
			var t = e.target && e.target.closest ? e.target.closest('[data-lgv-tip]') : null;
			tipTarget = t;
			if (t) showTip(t); else hideTip();
		});
		document.addEventListener('focusout', function () {
			tipTarget = null;
			hideTip();
		});
		document.addEventListener('scroll', function () {
			tipTarget = null;
			hideTip();
		}, true);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { init(); });
	} else {
		init();
	}

	window.LGV_SW = window.LGV_SW || {};
	window.LGV_SW.init = init;
})();
