/**
 * Lingua Visual — Descubridor de cadenas: progreso por lotes vía AJAX.
 */
(function ($) {
	'use strict';

	function setBar(pct) {
		$('#lgv-disc-bar-fill').css('width', Math.min(100, pct) + '%');
		$('#lgv-disc-bar-pct').text(Math.min(100, Math.round(pct)) + '%');
	}

	function statsLine(s) {
		return LGV_DISC.i18n.done
			.replace('%1$d', s.done).replace('%2$d', s.total)
			.replace('%3$d', s.found).replace('%4$d', s.new);
	}

	function runBatch() {
		$.post(LGV_DISC.ajaxurl, {
			action: 'lgv_discover_batch',
			nonce: LGV_DISC.nonce
		}).done(function (resp) {
			if (!resp || !resp.success) {
				$('#lgv-disc-msg').html('<div class="notice notice-error"><p>' +
					((resp && resp.data && resp.data.message) ? resp.data.message : LGV_DISC.i18n.error) + '</p></div>');
				$('#lgv-disc-start').prop('disabled', false);
				return;
			}
			var s = resp.data;
			var pct = s.total > 0 ? (s.done / s.total) * 100 : 100;
			setBar(pct);
			$('#lgv-disc-stats').text(statsLine(s));
			if (s.n_errors > 0) {
				$('#lgv-disc-errors').html('<p><strong>' + LGV_DISC.i18n.errors + ':</strong> ' + s.n_errors + '</p><ul><li>' +
					s.errors.map(function (e) { return $('<div>').text(e).html(); }).join('</li><li>') + '</li></ul>').show();
			}
			if (s.status === 'running') {
				runBatch();
			} else {
				setBar(100);
				$('#lgv-disc-msg').html('<div class="notice notice-success"><p>' + LGV_DISC.i18n.finished + '</p></div>');
				$('#lgv-disc-start').prop('disabled', false);
			}
		}).fail(function () {
			$('#lgv-disc-msg').html('<div class="notice notice-error"><p>' + LGV_DISC.i18n.error + '</p></div>');
			$('#lgv-disc-start').prop('disabled', false);
		});
	}

	$(function () {
		$('#lgv-disc-start').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			$btn.prop('disabled', true);
			$('#lgv-disc-msg').empty();
			$('#lgv-disc-errors').hide().empty();
			setBar(0);
			$('#lgv-disc-stats').text(LGV_DISC.i18n.starting);
			var mode = $('input[name="lgv_disc_mode"]:checked').val() || 'sitemap';
			$.post(LGV_DISC.ajaxurl, {
				action: 'lgv_discover_start',
				nonce: LGV_DISC.nonce,
				mode: mode,
				urls: $('#lgv-disc-urls').val()
			}).done(function (resp) {
				if (!resp || !resp.success) {
					$('#lgv-disc-msg').html('<div class="notice notice-error"><p>' +
						((resp && resp.data && resp.data.message) ? resp.data.message : LGV_DISC.i18n.error) + '</p></div>');
					$btn.prop('disabled', false);
					return;
				}
				runBatch();
			}).fail(function () {
				$('#lgv-disc-msg').html('<div class="notice notice-error"><p>' + LGV_DISC.i18n.error + '</p></div>');
				$btn.prop('disabled', false);
			});
		});
	});
})(jQuery);
