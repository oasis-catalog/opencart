<?php
namespace Opencart\Catalog\Controller\Extension\Oasiscatalog\Event;

require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/cli.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/api.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/main.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/config.php');

use Opencart\Admin\Controller\Extension\Oasis\Api;
use Opencart\Admin\Controller\Extension\Oasis\Config as OasisConfig;


class Event extends \Opencart\System\Engine\Controller
{
	private const ROUTE = 'extension/oasiscatalog/module/oasis';

	/**
	 * Event trigger: catalog/controller/product/thumb/before
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function controller_product_thumb(&$route = false, &$data = array()) {
		$this->load->model(self::ROUTE);

		foreach($data as $key => $item){
			$images = $this->model_extension_oasiscatalog_module_oasis->getImgsCDN($item['product_id']);
			if(!empty($images)){
				$main_img = array_filter($images, fn($n) => $n['main'])[0] ?? $images[0];
				$data[$key]['thumb'] = $main_img['url_superbig'];
			}
		}
	}

	/**
	 * Event trigger: catalog/view/product/product/before
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function product_product(&$route = false, &$data = array()) {
		$this->load->model(self::ROUTE);

		$images = $this->model_extension_oasiscatalog_module_oasis->getImgsCDN($data['product_id']);
		if(!empty($images)){
			$main_img = array_filter($images, fn($n) => $n['main'])[0] ?? $images[0];

			$data['popup'] = $main_img['url_superbig'];
			$data['thumb'] = $main_img['url_big'];

			$data['images'] =[];
			foreach ($images as $img) {
				$data['images'][] = [
					'popup' => $img['url_superbig'],
					'thumb' => $img['url_thumbnail']
				];
			}
		}
	}

	/**
	 * Event trigger: catalog/view/checkout/cart_list/before
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function checkout_cart_list(&$route = false, &$data = array()) {
		$this->load->model(self::ROUTE);
		foreach($data['products'] as $key => $item){
			$images = $this->model_extension_oasiscatalog_module_oasis->getImgsCDN($item['product_id']);
			if(!empty($images)){
				$main_img = array_filter($images, fn($n) => $n['main'])[0] ?? $images[0];
				$data['products'][$key]['thumb'] = $main_img['url_thumbnail'];
			}
		}
	}

	/**
	 * Event trigger: catalog/view/common/cart/before
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function common_cart(&$route = false, &$data = array()) {
		$this->load->model(self::ROUTE);

		foreach($data['products'] as $key => $item){
			$images = $this->model_extension_oasiscatalog_module_oasis->getImgsCDN($item['product_id']);
			if(!empty($images)){
				$main_img = array_filter($images, fn($n) => $n['main'])[0] ?? $images[0];
				$data['products'][$key]['thumb'] = $main_img['url_thumbnail'];
			}
		}
	}

	/**
	 * Event trigger: catalog/view/account/wishlist_list/before
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function account_wishlist_list(&$route = false, &$data = array()) {
		$this->load->model(self::ROUTE);

		foreach($data['products'] as $key => $item){
			$images = $this->model_extension_oasiscatalog_module_oasis->getImgsCDN($item['product_id']);
			if(!empty($images)){
				$main_img = array_filter($images, fn($n) => $n['main'])[0] ?? $images[0];
				$data['products'][$key]['thumb'] = $main_img['url_thumbnail'];
			}
		}
	}

	/**
	 * Event trigger: catalog/view/product/compare/before
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function product_compare(&$route = false, &$data = array()) {
		$this->load->model(self::ROUTE);

		foreach($data['products'] as $key => $item){
			$images = $this->model_extension_oasiscatalog_module_oasis->getImgsCDN($item['product_id']);
			if(!empty($images)){
				$main_img = array_filter($images, fn($n) => $n['main'])[0] ?? $images[0];
				$data['products'][$key]['thumb'] = $main_img['url_thumbnail'];
			}
		}
	}



	/* Branding */

	/**
	 * Event trigger: catalog/controller/product/product/before
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function controller_product_product(&$route = false, &$data = array()) {
		$this->document->addScript('/extension/oasiscatalog/catalog/view/javascript/widget.js');
		$this->document->addScript('//unpkg.com/@oasis-catalog/branding-widget@1.3.0/client/index.iife.js');
		$this->document->addStyle('//unpkg.com/@oasis-catalog/branding-widget@1.3.0/client/style.css');
	}

	/**
	 * Event trigger: catalog/view/product/product/after
	 * @param  mixed $route
	 * @param  mixed $data
	 * @param  mixed $output
	 * @return void
	 */
	public function product_product_after(&$route, &$data, &$output) {
		$this->load->model('setting/setting');
		$setting = $this->model_setting_setting->getSetting('module_oasis');
		$branding_box = $setting['module_oasis_opt']['branding_selector'] ?? '';

		$script = "<script type='text/javascript'>
			if(!OaHelper){
			    var OaHelper = {};
			}
			OaHelper.branding_box = '{$branding_box}';
		</script>";
		$output = str_replace('</head>', $script . '</head>', $output);
	}

	/**
	 * Event trigger: catalog/controller/checkout/cart.add/after
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function checkout_cart_add_after(&$route, &$data, &$output) {
		$product_id = $this->request->post['product_id'] ?? null;
		$branding	= $this->request->post['branding'] ?? null;
		if (empty($product_id) || empty($branding)) {
			return;
		}

		if (isset($this->request->post['option'])) {
			$option = array_filter((array)$this->request->post['option']);
		} else {
			$option = [];
		}

		$cart_id = null;
		foreach ($this->cart->getProducts() as $cart_item) {
			if ($product_id == $cart_item['product_id']) {
				if ($this->compareOptions($option, $cart_item['option'])) {
					$cart_id = $cart_item['cart_id'];
					break;
				}
			}
		}
		if (empty($cart_id)) {
			return;
		}

		$this->load->model(self::ROUTE);
		OasisConfig::instance($this->registry, [
			'init' => true
		]);
		$product_oasis = $this->model_extension_oasiscatalog_module_oasis->getOasisProduct($product_id, $option);
		$branding_data = Api::getBrandingCoef($product_oasis['product_id_oasis']);
		$labels = [];
		if ($branding_data) {
			foreach ($branding as $branding_item) {
				foreach ($branding_data->methods as $method) {
					foreach ($method->types as $type) {
						if ($branding_item['typeId'] == $type->id) {
							$labels[] = $type->name;
							break 2;
						}
					}
				}
			}
		}
		$this->model_extension_oasiscatalog_module_oasis->updateCartBranding($cart_id, $branding, implode(', ', $labels));
	}

	/**
	 * Event trigger: catalog/controller/checkout/cart.edit/after
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function checkout_cart_edit_after(&$route, &$data, &$output) {
		if (isset($this->request->post['key'])) {
			$cart_id = (int)$this->request->post['key'];
		} else {
			$cart_id = 0;
		}
		if (!empty($cart_id)) {
			$this->load->model(self::ROUTE);
			$this->model_extension_oasiscatalog_module_oasis->clearCartBrandingPrice($cart_id);
		}
	}

	/**
	 * Event trigger: catalog/model/checkout/cart.getProducts/after
	 * @param  mixed $route
	 * @param  mixed $data
	 * @param  mixed $output
	 * @return void
	 */
	public function checkout_cart_getProducts_after(&$route, &$data, &$output) {
		$this->load->model(self::ROUTE);

		foreach (($output ?? []) as $key => $item) {
			$cart_branding = $this->model_extension_oasiscatalog_module_oasis->getCartBranding($item['cart_id']);
			if (!empty($cart_branding)) {
				if(empty($output[$key]['option'])) {
					$output[$key]['option'] = [];
				}
				$this->load->language(self::ROUTE);
				$output[$key]['option'][] = [
					'type'	=> 'custom',
					'name'	=> $this->language->get('text_product_option_branding'),
					'value' => $cart_branding['label'] ?? '-',
				];
			}
		}
	}

	/**
	 * Event trigger: catalog/model/checkout/order.addOrder/after
	 * @param  mixed $route
	 * @param  mixed $data
	 * @param  mixed $output
	 * @return void
	 */
	public function checkout_order_addOrder_after(&$route, &$data, &$output) {
		$order_id = $output ?? null;
		$cart_data = $data[0] ?? null;
		
		if (!empty($order_id) && !empty($cart_data['products'])) {
			$this->load->model(self::ROUTE);
			$this->load->model('checkout/order');

			$order_products = $this->model_checkout_order->getProducts($order_id);


			foreach ($cart_data['products'] as $cart_item) {
				$cart_branding = $this->model_extension_oasiscatalog_module_oasis->getCartBranding($cart_item['cart_id']);
				if (!empty($cart_branding)) {

					foreach ($order_products as $order_item) {
						$order_item_options = $this->model_checkout_order->getOptions($order_id, $order_item['order_product_id']);
						if ($this->compareOptions($cart_item['option'], $order_item_options, true)) {
							$this->model_extension_oasiscatalog_module_oasis->updateOrderBranding($order_item['order_product_id'], $cart_branding['branding'], $cart_branding['label']);
							break;
						}
					}
				}
			}
		}
	}


	/**
	 * @param  array $opt0
	 * @param  array $opt1
	 * @param  bool $combine_first
	 * @return bool
	 */
	private function compareOptions(array $opt0 = [], array $opt1 = [], bool $combine_first = false): bool {
		if ($combine_first) {
			$opt0 = array_combine(array_map(fn($x) => $x['product_option_id'], $opt0),
							array_map(fn($x) => $x['product_option_value_id'], $opt0));
		}
		$opt1 = array_combine(array_map(fn($x) => $x['product_option_id'], $opt1),
							array_map(fn($x) => $x['product_option_value_id'], $opt1));
		return $opt0 == $opt1;
	}
}