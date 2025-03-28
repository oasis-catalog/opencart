if(!OaHelper){
	var OaHelper = {};
}

(function ($) {
	let CLASS_HANDLE_P = 'oa-tree-handle-p',
		CLASS_HANDLE_M = 'oa-tree-handle-m',
		CLASS_TREE_NODE = 'oa-tree-node',
		CLASS_TREE_LEAF = 'oa-tree-leaf',
		CLASS_TREE_CHILDS = 'oa-tree-childs',
		CLASS_COLLAPSED = 'oa-tree-collapsed',

		CLASS_LABEL = 'oa-tree-label',
		CLASS_LABEL_RELATION_ACTIVE = 'relation-active',

		CLASS_CTRL_M = 'oa-tree-ctrl-m',
		CLASS_CTRL_P = 'oa-tree-ctrl-p',

		CLASS_RELATION = 'oa-tree-relation',
		CLASS_BTN_RELATION = 'oa-tree-btn-relation',

		CLASS_INP_CAT = 'oa-tree-cb-cat',
		CLASS_INP_REL = 'oa-tree-inp-rel';


	OaHelper.Tree = class {
		el_root = null;

		constructor (el_root, p) {
			this.el_root = el_root = $(el_root);
			p = p || {};

			el_root.find('.' + CLASS_HANDLE_P).on('click', evt => {
				$(evt.target).closest('.' + CLASS_TREE_NODE).toggleClass(CLASS_COLLAPSED, false);
				p.onSize && p.onSize.call(this);
			});
			el_root.find('.' + CLASS_HANDLE_M).on('click', evt => {
				$(evt.target).closest('.' + CLASS_TREE_NODE).toggleClass(CLASS_COLLAPSED, true);
				p.onSize && p.onSize.call(this);
			});

			el_root.find('.' + CLASS_CTRL_M).on('click', () => {
				el_root.find('.' + CLASS_TREE_NODE).addClass(CLASS_COLLAPSED);
				p.onSize && p.onSize.call(this);
			});
			el_root.find('.' + CLASS_CTRL_P).on('click', () => {
				el_root.find('.' + CLASS_TREE_NODE).removeClass(CLASS_COLLAPSED);
				p.onSize && p.onSize.call(this);
			});

			el_root.find('input[type="checkbox"]').on('change', (evt) => {
				let tree_node = $(evt.target).closest(`.${CLASS_TREE_NODE}, .${CLASS_TREE_LEAF}`);
				this.checkCheckbox(el_root, tree_node, evt.target.checked);
			});

			el_root.find('.' + CLASS_TREE_LEAF).each((i, node) =>{
				let el = $(node),
					inp_rel = el.find(`.${CLASS_LABEL}:first .${CLASS_INP_REL}`);

				inp_rel.prop('disabled', !inp_rel.val());
				this.checkStatusNode(el_root, el);
			});

			el_root.find('.' + CLASS_BTN_RELATION).on('click', (evt) => {
				evt.preventDefault();
				evt.stopPropagation();

				let tree_node = $(evt.target).closest(`.${CLASS_TREE_NODE}, .${CLASS_TREE_LEAF}`),
					cat_id = tree_node.find('.' + CLASS_INP_CAT).val(),
					val_rel = tree_node.find('.' + CLASS_INP_REL).val().split('_');

				let cat_rel_id = val_rel.length == 2 ? parseInt(val_rel[1]) : null;

				p.onBtnRelation && p.onBtnRelation.call(this, cat_id, cat_rel_id);
			});
		}

		checkCheckbox (el_root, tree_node, is_checked) {
			tree_node.find('input[type="checkbox"]').prop({
				checked: is_checked,
				indeterminate: false
			});		

			this.checkStatusNode(el_root, tree_node);
		}

		checkStatusNode (el_root, tree_node) {
			let parent_el = tree_node;
			while(true){
				parent_el = parent_el.parent();
				if(parent_el.is(el_root) || parent_el.length == 0){
					break;
				}
				if(parent_el.hasClass(CLASS_TREE_NODE)){
					let state = this.checkChildsStatus(parent_el),
						cb = parent_el.find(`.${CLASS_LABEL}:first input[type="checkbox"]`),
						inp_rel = parent_el.find(`.${CLASS_LABEL}:first .${CLASS_INP_REL}`);

					inp_rel.prop('disabled', !inp_rel.val());

					switch(state){
						case 'indeterminate':
							cb.prop({
								checked: false,
								indeterminate: true
							});
							break;
						case 'checked':
							cb.prop({
								checked: true,
								indeterminate: false
							});
							break;
						case 'unchecked':
							cb.prop({
								checked: false,
								indeterminate: false
							});
							break;
					}
				}
			}
		}

		checkChildsStatus (tree_node) {
			let arr = [];
			tree_node.find(`.${CLASS_TREE_CHILDS}:first input[type="checkbox"]`).each(function(i, node) {
				if(node.indeterminate){
					arr.push('indeterminate');
				}
				else if(node.checked){
					arr.push('checked');
				}
				else{
					arr.push('unchecked');
				}
			});
			return arr.includes('indeterminate') ? 'indeterminate' : 
					arr.includes('unchecked') ?
						(arr.includes('checked') ? 'indeterminate' : 'unchecked') :
						'checked';
		}

		setRelationItem (cat_id, item) {
			this.el_root.find('.' + CLASS_INP_CAT).each((i, inp_node) => {
				if(inp_node.value == cat_id){
					let tree_node = $(inp_node).closest(`.${CLASS_TREE_NODE}, .${CLASS_TREE_LEAF}`),
						el_label = tree_node.find('.' + CLASS_LABEL + ':first'),
						inp_rel = el_label.find('.' + CLASS_INP_REL),
						lebel_rel = el_label.find('.' + CLASS_RELATION);

					inp_rel.val(item ? (cat_id + '_' + item.value) : '');
					inp_rel.prop('disabled', !item);
					lebel_rel.text(item ? item.lebelPath : '');

					el_label.toggleClass(CLASS_LABEL_RELATION_ACTIVE, !!item);

					return false;
				}
			});
		}
	};


	OaHelper.RadioTree = class {
		el_root = null;

		constructor (el_root, p){
			this.el_root = el_root = $(el_root);
			p = p || {};

			el_root.find('.' + CLASS_HANDLE_P).on('click', (evt) => {
				$(evt.target).closest('.' + CLASS_TREE_NODE).toggleClass(CLASS_COLLAPSED, false);
				p.onSize && p.onSize.call(this);
			});
			el_root.find('.' + CLASS_HANDLE_M).on('click', (evt) => {
				$(evt.target).closest('.' + CLASS_TREE_NODE).toggleClass(CLASS_COLLAPSED, true);
				p.onSize && p.onSize.call(this);
			});

			el_root.find('.' + CLASS_CTRL_M).on('click', () => {
				el_root.find('.' + CLASS_TREE_NODE).addClass(CLASS_COLLAPSED);
				p.onSize && p.onSize.call(this);
			});
			el_root.find('.' + CLASS_CTRL_P).on('click', () => {
				el_root.find('.' + CLASS_TREE_NODE).removeClass(CLASS_COLLAPSED);
				p.onSize && p.onSize.call(this);
			});

			el_root.find('input[type="radio"]').on('change', (evt) => {
				if(evt.target.checked && p.onChange){
					p.onChange.call(this, {
						value: evt.target.value,
						lebelPath: this.getTreePath($(evt.target))
					});
				}
			});
		}

		getTreePath (input_el){
			let result = '',
				parent_el = input_el;
			while(true){
				parent_el = parent_el.parent();
				if(parent_el.is(this.el_root) || parent_el.length == 0){
					break;
				}
				if(parent_el.hasClass(CLASS_TREE_NODE) || parent_el.hasClass(CLASS_TREE_LEAF)){
					result = parent_el.find('label:first').text() + (result.length > 0 ?  ' / ' : '') + result;
				}
			}
			return result;
		}

		get value() {
			let radio_checked = this.el_root.find('input[type="radio"]:checked');
			if(radio_checked.length > 0){
				return radio_checked.val();
			}
			return null;
		}

		get item() {
			let radio_checked = this.el_root.find('input[type="radio"]:checked');
			if(radio_checked.length > 0){
				return {
					value: radio_checked.val(),
					lebelPath: this.getTreePath(radio_checked)
				};
			}

			return null;
		}

		set value(v) {
			this.el_root.find('[type="radio"]').each(function(){
				if(this.value == v){
					this.checked = true;
					return false;
				}
			});
		}
	}
})(jQuery);