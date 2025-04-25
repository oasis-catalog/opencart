<?php
namespace Opencart\Catalog\Controller\Extension\Oasiscatalog\Event;

class Event extends \Opencart\System\Engine\Controller
{
	private const ROUTE = 'extension/oasiscatalog/module/oasis';

	/**
	 * Event trigger: catalog/controller/product/thumb/before
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function product_thumb(&$route = false, &$data = array()) {
		$this->load->model(self::ROUTE);

		foreach($data as $key => $item){
			$images = $this->registry->model_extension_oasiscatalog_module_oasis->getImgsCDNFromID($item['product_id']);
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

		$images = $this->registry->model_extension_oasiscatalog_module_oasis->getImgsCDNFromID($data['product_id']);
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
			$images = $this->registry->model_extension_oasiscatalog_module_oasis->getImgsCDNFromID($item['product_id']);
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
			$images = $this->registry->model_extension_oasiscatalog_module_oasis->getImgsCDNFromID($item['product_id']);
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
			$images = $this->registry->model_extension_oasiscatalog_module_oasis->getImgsCDNFromID($item['product_id']);
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
			$images = $this->registry->model_extension_oasiscatalog_module_oasis->getImgsCDNFromID($item['product_id']);
			if(!empty($images)){
				$main_img = array_filter($images, fn($n) => $n['main'])[0] ?? $images[0];
				$data['products'][$key]['thumb'] = $main_img['url_thumbnail'];
			}
		}
	}
}