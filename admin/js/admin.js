/**
 * LFT Membership 管理画面用スクリプト
 * モーダル・トークン作成・会員追加/編集/削除・一時停止/再開
 */
(function($) {
	'use strict';

	var modal = $('#lft-membership-modal');
	var form = $('#lft-membership-form');
	var currentToken = '';

	// トークン生成を実行（新規モーダル用・自動または手動）
	function doGenerateToken(callback) {
		$.post(lftMembership.ajaxUrl, {
			action: 'lft_membership_generate_token',
			nonce: lftMembership.nonce
		})
		.done(function(res) {
			if (res.success && res.data && res.data.url) {
				currentToken = res.data.token;
				$('#lft-field-access-url').val(res.data.url);
			}
			if (typeof callback === 'function') callback(res);
		})
		.fail(function() {
			if (typeof callback === 'function') callback({ success: false });
		});
	}

	// モーダルを開く（新規）— 開いたら自動でトークンを作成
	function openModalForNew() {
		currentToken = '';
		form[0].reset();
		$('#lft-field-id').val('');
		$('#lft-field-access-url').val('').attr('placeholder', '読み込み中…');
		$('#lft-modal-title').text('ユーザー登録');
		$('.lft-btn-confirm').text('確認');
		$('#lft-form-row-token').show();
		$('#lft-btn-create-token').show();
		modal.addClass('is-open');
		doGenerateToken();
	}

	// モーダルを開く（編集用：データを埋める）
	function openModalForEdit(member) {
		currentToken = member.token || '';
		$('#lft-field-id').val(member.id);
		$('#lft-field-email').val(member.email || '');
		$('#lft-field-user-name').val(member.user_name || '');
		$('#lft-field-company').val(member.company_name || '');
		$('#lft-field-phone').val(member.phone || '');
		$('#lft-field-payment').val(member.payment_date || '');
		$('#lft-field-deadline').val(member.deadline || '');
		var baseUrl = (typeof lftMembership !== 'undefined' && lftMembership.baseUrl) ? lftMembership.baseUrl.replace(/\/$/, '') : '';
		var slug = (typeof lftMembership !== 'undefined' && lftMembership.slug) ? lftMembership.slug : 'lft_membership';
		var pathSegment = (member.wp_user_id && parseInt(member.wp_user_id, 10) > 0) ? 'confirmed_user' : 'new_user';
		$('#lft-field-access-url').val(baseUrl + '/' + slug + '/' + pathSegment + '/' + (member.token || ''));
		$('#lft-modal-title').text('ユーザー編集');
		$('.lft-btn-confirm').text('更新');
		$('#lft-form-row-token').show();
		$('#lft-btn-create-token').hide();
		modal.addClass('is-open');
	}

	function closeModal() {
		modal.removeClass('is-open');
	}

	// トークン作成ボタン（再生成）
	$('#lft-btn-create-token').on('click', function() {
		var btn = $(this);
		btn.prop('disabled', true);
		doGenerateToken(function(res) {
			if (!res.success) alert(lftMembership.i18n.error);
		});
		btn.prop('disabled', false);
	});

	// URLコピー（モーダル内）
	$('#lft-btn-copy-url').on('click', function() {
		var url = $('#lft-field-access-url').val();
		if (!url) return;
		copyToClipboard(url);
	});

	// テーブル内・モーダル内のコピーボタン（.lft-copy-url または 親の data-url）
	$(document).on('click', '.lft-copy-url, .lft-copy-btn', function() {
		if ($(this).hasClass('lft-recreate-token')) return;
		var url = $(this).data('url');
		if (!url) url = $('#lft-field-access-url').val();
		if (url) copyToClipboard(url);
	});

	// トークン再発行（アイコンのみ）
	$(document).on('click', '.lft-recreate-token', function(e) {
		e.preventDefault();
		var btn = $(this);
		var id = btn.data('id');
		if (!id) return;
		btn.prop('disabled', true);
		$.post(lftMembership.ajaxUrl, {
			action: 'lft_membership_recreate_token',
			nonce: lftMembership.nonce,
			id: id
		})
		.done(function(res) {
			if (res.success && res.data && res.data.url) {
				var row = btn.closest('tr');
				row.find('.lft-copy-url').data('url', res.data.url);
				var short = res.data.token && res.data.token.length > 18 ? res.data.token.substring(0, 18) + '...' : (res.data.token || '');
				row.find('.lft-token-short').text(short);
				alert(lftMembership.i18n.recreateSuccess || 'トークンを再発行しました。');
			} else {
				alert(res.data && res.data.message ? res.data.message : lftMembership.i18n.error);
			}
		})
		.fail(function() {
			alert(lftMembership.i18n.error);
		})
		.always(function() {
			btn.prop('disabled', false);
		});
	});

	function copyToClipboard(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function() {
				alert(lftMembership.i18n.copySuccess);
			}).catch(function() {
				fallbackCopy(text);
			});
		} else {
			fallbackCopy(text);
		}
	}

	function fallbackCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		try {
			document.execCommand('copy');
			alert(lftMembership.i18n.copySuccess);
		} catch (e) {}
		document.body.removeChild(ta);
	}

	// フォーム送信（新規追加 or 更新）
	form.on('submit', function(e) {
		e.preventDefault();
		var id = $('#lft-field-id').val();
		var isEdit = id && id.length > 0;

		var payload = {
			action: isEdit ? 'lft_membership_update_member' : 'lft_membership_add_member',
			nonce: lftMembership.nonce,
			email: $('#lft-field-email').val(),
			user_name: $('#lft-field-user-name').val(),
			company_name: $('#lft-field-company').val(),
			phone: $('#lft-field-phone').val(),
			payment_date: $('#lft-field-payment').val(),
			deadline: $('#lft-field-deadline').val()
		};

		if (isEdit) {
			payload.id = id;
		} else {
			if (!currentToken) {
				alert('「トークンを作成」をクリックしてアクセスURLを生成してください。');
				return;
			}
			payload.token = currentToken;
		}

		$.post(lftMembership.ajaxUrl, payload)
		.done(function(res) {
			if (res.success) {
				alert(res.data.message || '保存しました。');
				closeModal();
				location.reload();
			} else {
				alert(res.data && res.data.message ? res.data.message : lftMembership.i18n.error);
			}
		})
		.fail(function() {
			alert(lftMembership.i18n.error);
		});
	});

	// 追加ボタン
	$('#lft-membership-btn-add').on('click', function() {
		openModalForNew();
	});

	// モーダル閉じる
	$('.lft-membership-modal__close, .lft-membership-modal__backdrop, .lft-btn-cancel').on('click', function() {
		closeModal();
	});

	// 編集リンク（AJAXで1件取得してモーダルを開く）
	$(document).on('click', '.lft-edit', function(e) {
		e.preventDefault();
		var id = $(this).data('id');
		$.post(lftMembership.ajaxUrl, {
			action: 'lft_membership_get_member',
			nonce: lftMembership.nonce,
			id: id
		})
		.done(function(res) {
			if (res.success && res.data && res.data.member) {
				openModalForEdit(res.data.member);
			} else {
				alert(res.data && res.data.message ? res.data.message : lftMembership.i18n.error);
			}
		})
		.fail(function() {
			alert(lftMembership.i18n.error);
		});
	});

	// 削除
	$(document).on('click', '.lft-delete', function(e) {
		e.preventDefault();
		if (!confirm(lftMembership.i18n.confirmDelete)) return;
		var id = $(this).data('id');
		$.post(lftMembership.ajaxUrl, {
			action: 'lft_membership_delete_member',
			nonce: lftMembership.nonce,
			id: id
		})
		.done(function(res) {
			if (res.success) {
				location.reload();
			} else {
				alert(res.data && res.data.message ? res.data.message : lftMembership.i18n.error);
			}
		})
		.fail(function() {
			alert(lftMembership.i18n.error);
		});
	});

	// 一時停止 / 再開
	$(document).on('click', '.lft-toggle-status', function() {
		var btn = $(this);
		var id = btn.data('id');
		btn.prop('disabled', true);
		$.post(lftMembership.ajaxUrl, {
			action: 'lft_membership_toggle_status',
			nonce: lftMembership.nonce,
			id: id
		})
		.done(function(res) {
			if (res.success) {
				location.reload();
			} else {
				alert(res.data && res.data.message ? res.data.message : lftMembership.i18n.error);
			}
		})
		.fail(function() {
			alert(lftMembership.i18n.error);
		})
		.always(function() {
			btn.prop('disabled', false);
		});
	});

})(jQuery);
