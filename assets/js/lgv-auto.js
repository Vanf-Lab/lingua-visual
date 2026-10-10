/**
 * Lingua Visual — Traducción automática: lotes vía AJAX con freno de cuota.
 */
(function ($) {
	'use strict';

	var totalTranslated = 0;
	var totalChars = 0;

	function runBatch() {
		$.post(LGV_AUTO.ajaxurl, {
			action: 'lgv_auto_batch',
			nonce: LGV_AUTO.nonce,
			lang: $('#lgv-auto-lang').val()
		}).done(function (resp) {
			if (!resp || !resp.success) {
				var msg = (resp && resp.data && resp.data.message) ? resp.data.message : LGV_AUTO.i18n.error;
				var cls = (resp && resp.data && resp.data.quota_hit) ? 'notice-warning' : 'notice-error';
				$('#lgv-auto-msg').html('<div class="notice ' + cls + '"><p>' + $('<div>').text(msg).html() + '</p></div>');
				$('#lgv-auto-start').prop('disabled', false);
				return;
			}
			var s = resp.data;
			totalTranslated += s.translated;
			totalChars += s.chars;
			$('#lgv-auto-stats').text(
				LGV_AUTO.i18n.progress.replace('%1$d', totalTranslated).replace('%2$d', totalChars).replace('%3$d', s.remaining)
			);
			if (s.n_errors > 0) {
				$('#lgv-auto-errors').html('<p><strong>' + LGV_AUTO.i18n.errors + ':</strong></p><ul><li>' +
					s.errors.map(function (e) { return $('<div>').text(e).html(); }).join('</li><li>') + '</li></ul>').show();
			}
			if (s.quota_hit) {
				$('#lgv-auto-msg').html('<div class="notice notice-warning"><p>' + LGV_AUTO.i18n.quota + '</p></div>');
				$('#lgv-auto-start').prop('disabled', false);
				return;
			}
			if (s.remaining > 0 && !s.done) {
				runBatch();
			} else {
				$('#lgv-auto-msg').html('<div class="notice notice-success"><p>' + LGV_AUTO.i18n.finished + '</p></div>');
				$('#lgv-auto-start').prop('disabled', false);
			}
		}).fail(function () {
			$('#lgv-auto-msg').html('<div class="notice notice-error"><p>' + LGV_AUTO.i18n.error + '</p></div>');
			$('#lgv-auto-start').prop('disabled', false);
		});
	}

	$(function () {
		$('#lgv-auto-start').on('click', function (e) {
			e.preventDefault();
			if (!confirm(LGV_AUTO.i18n.confirm)) {
				return;
			}
			var $btn = $(this);
			$btn.prop('disabled', true);
			totalTranslated = 0;
			totalChars = 0;
			$('#lgv-auto-msg').empty();
			$('#lgv-auto-errors').hide().empty();
			$('#lgv-auto-stats').text(LGV_AUTO.i18n.starting);
			runBatch();
		});
	});
})(jQuery);
