/**
 * Lingua Visual — Alpha para el color picker de WordPress (Iris).
 *
 * Extensión propia (sin dependencias externas): agrega un slider de opacidad
 * dentro del panel del picker. Cuando la opacidad es 100% guarda hex clásico
 * (backward compatible); si no, guarda rgba().
 *
 * Uso: $('.lgv-color').lgvAlphaPicker();
 */
(function ($) {
	'use strict';

	function clamp(v, min, max) {
		v = parseFloat(v);
		if (isNaN(v)) v = min;
		return Math.min(max, Math.max(min, v));
	}

	function parseColor(str) {
		str = String(str || '').trim();
		var m;
		m = str.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([0-9]*\.?[0-9]+))?\s*\)$/i);
		if (m) {
			return {
				r: clamp(m[1], 0, 255) | 0,
				g: clamp(m[2], 0, 255) | 0,
				b: clamp(m[3], 0, 255) | 0,
				a: m[4] === undefined || m[4] === '' ? 1 : clamp(m[4], 0, 1)
			};
		}
		m = str.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
		if (m) {
			var h = m[1];
			if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
			return { r: parseInt(h.substr(0, 2), 16), g: parseInt(h.substr(2, 2), 16), b: parseInt(h.substr(4, 2), 16), a: 1 };
		}
		if (str.toLowerCase() === 'transparent') return { r: 0, g: 0, b: 0, a: 0 };
		return null;
	}

	function toHex(c) {
		function h(n) { n = Math.round(n); return ('0' + n.toString(16)).slice(-2); }
		return '#' + h(c.r) + h(c.g) + h(c.b);
	}

	function toValue(c) {
		if (c.a >= 0.995) return toHex(c);
		var a = Math.round(c.a * 100) / 100;
		return 'rgba(' + c.r + ', ' + c.g + ', ' + c.b + ', ' + a + ')';
	}

	function checkerCSS() {
		return 'repeating-conic-gradient(#d4d4d4 0% 25%, #ffffff 0% 50%) 0 0 / 16px 16px';
	}

	$.fn.lgvAlphaPicker = function () {
		return this.each(function () {
			var $input = $(this);
			if ($input.data('lgv-alpha-done')) return;
			$input.data('lgv-alpha-done', true);

			var state = parseColor($input.val()) || { r: 255, g: 255, b: 255, a: 1 };

			$input.wpColorPicker({
				change: function (event, ui) {
					var c = parseColor(ui && ui.color ? ui.color.toString() : $input.val());
					if (!c) return;
					c.a = state.a; // la opacidad la manda el slider
					state = c;
					writeValue();
					syncUI();
				},
				clear: function () {
					state = { r: 255, g: 255, b: 255, a: 1 };
					syncUI();
				}
			});

			var $container = $input.closest('.wp-picker-container');
			var $holder = $container.find('.wp-picker-holder');
			var $wrap = $('<div class="lgv-alpha-wrap"><label class="lgv-alpha-label">Opacidad <span class="lgv-alpha-val"></span></label>' +
				'<input type="range" class="lgv-alpha-slider" min="0" max="100" step="1" aria-label="Opacidad"></div>');
			$holder.append($wrap);
			var $slider = $wrap.find('.lgv-alpha-slider');
			var $val = $wrap.find('.lgv-alpha-val');

			function writeValue() {
				$input.val(toValue(state)).trigger('change');
			}

			function syncUI() {
				var pct = Math.round(state.a * 100);
				$slider.val(pct);
				$val.text(pct + ' %');
				$slider.css('background',
					'linear-gradient(to right, rgba(' + state.r + ',' + state.g + ',' + state.b + ',0), rgba(' + state.r + ',' + state.g + ',' + state.b + ',1)), ' + checkerCSS());
			}

			$slider.on('input change', function () {
				state.a = clamp($slider.val(), 0, 100) / 100;
				writeValue();
				syncUI();
			});

			// Si el usuario escribe el valor a mano, re-parsear.
			$input.on('change', function () {
				var c = parseColor($input.val());
				if (!c) return;
				// Evitar loop: solo actualizar si cambió algo real.
				if (c.r !== state.r || c.g !== state.g || c.b !== state.b || Math.abs(c.a - state.a) > 0.001) {
					state = c;
					try { $input.wpColorPicker('color', toHex(c)); } catch (e) {}
					syncUI();
				}
			});

			syncUI();
		});
	};

	// Setter programático compatible con presets: $('#x').lgvSetColor('rgba(...)').
	$.fn.lgvSetColor = function (value) {
		return this.each(function () {
			var $input = $(this);
			var c = parseColor(value);
			if (!c) return;
			try { $input.wpColorPicker('color', toHex(c)); } catch (e) {}
			$input.val(value).trigger('change');
			// Re-sincronizar el slider si ya se inicializó.
			var $container = $input.closest('.wp-picker-container');
			var $slider = $container.find('.lgv-alpha-slider');
			if ($slider.length) {
				$slider.val(Math.round(c.a * 100)).trigger('input');
			}
		});
	};
})(jQuery);
