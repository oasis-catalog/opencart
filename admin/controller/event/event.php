<?php
namespace Opencart\Admin\Controller\Extension\Oasiscatalog\Event;

class Event extends \Opencart\System\Engine\Controller
{
	private const ROUTE = 'extension/oasiscatalog/module/oasis';

	/**
     * Event trigger: admin/view/catalog/product.delete/after
     * @param  mixed $route
     * @param  mixed $data
     * @return void
     */
	public function catalog_product_deleteProduct(&$route = false, &$data = array()) {
		if($productId = $data[0]){
			$this->load->model(self::ROUTE);
			$this->load->model('catalog/product');

			$rows = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProductsForOCID($productId);
			foreach ($rows as $row) {
				$this->registry->model_extension_oasiscatalog_module_oasis->deleteOasisProduct($row['product_id_oasis']);
			}
			$productImages = $this->registry->model_catalog_product->getImages($productId);
			foreach ($productImages as $img) {
				if (!empty($img['image'])) {
					$ext = pathinfo($img['image']);
					if (file_exists(DIR_IMAGE . $img['image'])) {
						unlink(DIR_IMAGE . $img['image']);
					}
					$this->registry->model_extension_oasiscatalog_module_oasis->deleteImage($ext['basename']);
				}
			}
		}
	}

	/**
     * Event trigger: admin/view/catalog/product_list/before
     * @param  mixed $route
     * @param  mixed $data
     * @return void
     */
	public function catalog_product_list(&$route = false, &$data = array()) {
		$this->load->model(self::ROUTE);

		foreach($data['products'] as $key => $item){
			$images = $this->registry->model_extension_oasiscatalog_module_oasis->getImgsCDN($item['product_id']);
			if(!empty($images)){
				$main_img = array_filter($images, fn($n) => $n['main'])[0] ?? $images[0];
				$data['products'][$key]['image'] = $main_img['url_thumbnail'];
			}
		}
	}

	/**
     * Event trigger: admin/view/catalog/product_form/before
     * @param  mixed $route
     * @param  mixed $data
     * @return void
     */
	public function catalog_product_form(&$route = false, &$data = array()) {
		$this->load->model(self::ROUTE);

		$images = $this->registry->model_extension_oasiscatalog_module_oasis->getImgsCDN($data['product_id']);
		if(!empty($images)){
			$main_img = array_filter($images, fn($n) => $n['main'])[0] ?? $images[0];

			$data['thumb'] = $main_img['url_big'];

			$data['product_images'] = [];
			foreach ($images as $img) {
				$data['product_images'][] = [
					'thumb' => $img['url_big'],
					'image' => 'not'
				];
			}
		}
	}


	/* Branding */

	/**
	 * Event trigger: admin/view/sale/order_info/before
	 * @param  mixed $route
	 * @param  mixed $data
	 * @return void
	 */
	public function sale_order_info(&$route, &$data) {
		$this->load->model(self::ROUTE);
		$this->load->language(self::ROUTE);

		foreach ($data['order_products'] as $key => $product) {
			$order_branding = $this->model_extension_oasiscatalog_module_oasis->getOrderBranding($product['order_product_id']);
			if (!empty($order_branding)) {
				if(empty($data['order_products'][$key]['option'])) {
					$data['order_products'][$key]['option'] = [];
				}
				$data['order_products'][$key]['option'][] = [
					'type'	=> 'custom',
					'name'	=> $this->language->get('text_product_option_branding'),
					'value' => $order_branding['label'] ?? '-',
				];
			}
		}
	}
}