jQuery(function ($) {
	let OaHelper = window.OaHelper || {
		branding_box: null
	};

	if (!OaHelper.branding_box && $(OaHelper.branding_box).length == 0) {
		return;
	}

	let form = $('#form-product');
	if (form.length == 0) {
		return;
	}

	let branding = OaHelper.branding = {
		update: Update,
		clear: Clear,
		getFormInfo: GetFormInfo,
	};

	form.find('input, select').on('change', () => branding.update());

	function Update() {
		$.ajax({
			url: 'index.php?route=extension/oasiscatalog/ajax/branding.get_info',
			type: 'POST',
			dataType: 'json',
			data: branding.getFormInfo(),
			success: function (data) {
				branding.clear();
				if (data && data.productId) {
					let cl = 'js--oasis-client-branding-widget';
					branding.node = $(`<div class="oasis-client-branding-widget"><div class="${cl}"></div></div>`);
					$(OaHelper.branding_box).append(branding.node);

					OasisBrandigWidget('.' + cl, {
						productId: data.productId,
						locale: OaHelper.locale ||'ru-RU',
						currency: OaHelper.currency || 'RUB'
					});
				}
			},
			error: function (error) {
				branding.clear();
            }
		});
	}

	function Clear() {
		if (branding.node) {
			branding.node.remove();
			branding.node = null;
		}
	}

	function GetFormInfo() {
		return form.serializeArray();
	}
});