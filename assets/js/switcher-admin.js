/**
 * Lingua Visual — Switcher Studio (admin): live preview, presets, orden drag&drop.
 */
(function ($) {
	'use strict';

	var timer = null;

	function collect() {
		var data = {
			action: 'lgv_switcher_preview',
			nonce: LGV_SW_ADMIN.nonce
		};
		$('#lgv-switcher-form').find('input,select,textarea').not('[type="file"]').each(function () {
			var $el = $(this), name = $el.attr('name');
			if (!name) return;
			// Convertimos sw[foo] en data.sw.foo para PHP.
			var m = name.match(/^sw\[([a-z0-9_]+)\](\[\])?$/);
			if (m) {
				var key = m[1];
				if (m[2]) {
					data['sw[' + key + '][]'] = data['sw[' + key + '][]'] || [];
					if ($el.is(':checkbox')) {
						if ($el.is(':checked')) data['sw[' + key + '][]'].push($el.val());
					} else {
						data['sw[' + key + '][]'].push($el.val());
					}
				} else if ($el.is(':checkbox')) {
					if ($el.is(':checked')) data['sw[' + key + ']'] = $el.val();
				} else if ($el.is(':radio')) {
					if ($el.is(':checked')) data['sw[' + key + ']'] = $el.val();
				} else {
					data['sw[' + key + ']'] = $el.val();
				}
			}
		});
		return data;
	}

	function refreshPreview() {
		var $stage = $('#lgv-sw-live');
		if (!$stage.length) return;
		$stage.css('opacity', '.45');
		$.post(LGV_SW_ADMIN.ajaxurl, collect())
			.done(function (res) {
				if (res && res.success) {
					$stage.html(res.data.html);
					if (window.LGV_SW && window.LGV_SW.init) window.LGV_SW.init($stage[0]);
				} else {
					var detail = (res && typeof res.data !== 'undefined') ? JSON.stringify(res.data).substring(0, 200) : 'no-data';
					$stage.html('<p class="lgv-preview-error">Preview error: ' + detail + '</p>');
					if (window.console) console.error('LGV preview response', res);
				}
			})
			.fail(function (xhr) {
				$stage.html('<p class="lgv-preview-error">Preview failed (HTTP ' + xhr.status + '). Check the browser console.</p>');
				if (window.console) console.error('LGV preview AJAX failed', xhr);
			})
			.always(function () {
				$stage.css('opacity', '1');
			});
	}

	function schedule() {
		clearTimeout(timer);
		timer = setTimeout(refreshPreview, 350);
	}

	$(function () {
		// Color pickers con soporte de transparencia (alpha).
		if ($.fn.lgvAlphaPicker) $('.lgv-color').lgvAlphaPicker();
		else if ($.fn.wpColorPicker) $('.lgv-color').wpColorPicker();

		// Orden drag&drop de idiomas.
		var $order = $('#lgv-lang-order');
		if ($order.length && $.fn.sortable) {
			$order.sortable({ handle: '.lgv-drag', axis: 'y', update: schedule });
		}

		// Live preview ante cualquier cambio.
		$('#lgv-switcher-form').on('change input', 'input,select,textarea', schedule);

		// Presets de un clic.
		$('.lgv-preset').on('click', function (e) {
			e.preventDefault();
			var values = $(this).data('preset');
			if (!values) return;
			$.each(values, function (key, val) {
				var $f = $('[name="sw[' + key + ']"]');
				if (!$f.length) return;
				if ($f.is(':checkbox')) {
					$f.prop('checked', !!val).trigger('change');
				} else if ($f.is(':radio')) {
					$f.filter('[value="' + val + '"]').prop('checked', true);
				} else {
					$f.val(val);
					if ($f.hasClass('lgv-color')) {
						if ($f.lgvSetColor) $f.lgvSetColor(val);
						else $f.wpColorPicker('color', val);
					}
				}
			});
			schedule();
		});

		// Ancho de preview: escritorio / móvil.
		$('.lgv-device').on('click', function (e) {
			e.preventDefault();
			$('.lgv-device').removeClass('button-primary');
			$(this).addClass('button-primary');
			$('#lgv-preview-stage').css('max-width', $(this).data('width'));
		});

		// Primera carga.
		refreshPreview();
	});
})(jQuery);
