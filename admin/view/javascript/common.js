jQuery(function ($) {
	let tree = new OaHelper.Tree('#tree', {
		onBtnRelation (cat_id, cat_rel_id){
			ModalRelation(cat_rel_id).then(item => tree.setRelationItem(cat_id, item));
		}
	});

	$('#copy-cron-product').on('click', () => {
		$('#input-cron-product').select();
		document.execCommand("copy");
	});

	$('#copy-cron-stock').on('click', () => {
		$('#input-cron-stock').select();
		document.execCommand("copy");
	});

	$('#cf_opt_category_rel').on('click', function(){
		let el_value = $(this).find('input[type="hidden"]'),
			el_label = $(this).find('.oa-category-rel'),
			cat_rel_id = el_value.val();

		cat_rel_id = cat_rel_id ? parseInt(cat_rel_id) : null;

		ModalRelation(cat_rel_id).then(item => {
			el_value.val(item ? item.value : '');
			el_label.text(item ? item.lebelPath : '');
		});
	});

	if($('#input-no-vat').is(':checked')){
		$('#tax-class').show();
	}
	$('#input-no-vat').click(function () {
		if ($(this).is(':checked')) {
			$('#tax-class').show(100);
		} else {
			$('#tax-class').hide(100);
		}
	});

	$('#rating-group input:checkbox').click(function () {
		if ($(this).is(':checked')) {
			$('#rating-group input:checkbox').not(this).prop('checked', false);
		}
	});




	setTimeout(upAjaxProgressBar, 10000);
	function upAjaxProgressBar() {
		jQuery(function ($) {
			$.ajax({
				url: 'index.php?route=extension/oasiscatalog/module/oasis|get_data_progress_bar&user_token=' + user_token,
				type: 'POST',
				dataType: 'json',
				success: function (data) {
					if (data) {
						document.getElementById('upAjaxStep').style.width = data.p_step + '%';
						$('#upAjaxStep').html(data.p_step + '%');

						document.getElementById('upAjaxTotal').style.width = data.p_total + '%';
						$('#upAjaxTotal').html(data.p_total + '%');

						document.querySelector(".oasis-process-icon").innerHTML = data.progress_icon;

						document.querySelector('.oasis-process-text').innerHTML = data.step_text;

						if (data.is_process) {
							switchAnimatedBar('progress-bar-striped progress-bar-animated', 'add');
							setTimeout(upAjaxProgressBar, 5000);
						} else {
							switchAnimatedBar('progress-bar-striped progress-bar-animated', 'remove');
							setTimeout(upAjaxProgressBar, 60000);
						}
					} else {
						switchAnimatedBar('progress-bar-striped progress-bar-animated', 'remove');
						setTimeout(upAjaxProgressBar, 600000);
					}
				}
			});
		});
	}

	function switchAnimatedBar(classStr, method) {
		let lassArr = classStr.split(' ');

		lassArr.forEach(function (item, index, array) {
			let upAjaxTotal = document.getElementById('upAjaxTotal');

			if (upAjaxTotal) {
				if (upAjaxTotal.classList.contains(item) && method === 'remove') {
					upAjaxTotal.classList.remove(item);
				} else if (method === 'add') {
					upAjaxTotal.classList.add(item);
				}
			}

			let upAjaxStep = document.getElementById('upAjaxStep');

			if (upAjaxStep) {
				if (upAjaxStep.classList.contains(item) && method === 'remove') {
					upAjaxStep.classList.remove(item);
				} else if (method === 'add') {
					upAjaxStep.classList.add(item);
				}
			}
		});
	}





	function ModalRelation(cat_rel_id){
		return new Promise((resolve, reject) => {
			$.post('index.php?route=extension/oasiscatalog/module/oasis|get_all_categories&user_token=' + user_token, null,
				tree_content => {
				let content = $('#oasis-relation').clone();
				content.find('.modal-body').html(tree_content);

				let btn_ok = content.find('.js-ok'),
					btn_clear = content.find('.js-clear'),
					modal = null,
					tree = new OaHelper.RadioTree(content.find('.oa-tree'), {
							onChange: item => {
								btn_ok.toggleClass('disabled', !item);
							}
						});

				tree.value = cat_rel_id;

				btn_ok.toggleClass('disabled', !tree.value);
				btn_clear.toggle(!!cat_rel_id);

				btn_ok.on('click', () => {
					let item = tree.item;
					if(item){
						modal.hide();
						resolve(item);
					}
				});
				btn_clear.on('click', () => {
					modal.hide();
					resolve(null);
				});

				modal = new bootstrap.Modal(content);
				modal.show();
			});
		});
	}
});