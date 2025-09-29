<?php
namespace Opencart\Catalog\Model\Extension\Oasiscatalog\Total;

require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/cli.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/api.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/main.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/config.php');

use Opencart\Admin\Controller\Extension\Oasis\Api;
use Opencart\Admin\Controller\Extension\Oasis\Config as OasisConfig;


class OasisBranding extends \Opencart\System\Engine\Model
{
	private const ROUTE = 'extension/oasiscatalog/module/oasis';

	public function getTotal(array &$totals, array &$taxes, float &$total): void
	{
		$this->load->model(self::ROUTE);

		$price = 0;
		foreach ($this->cart->getProducts() as $cart_item) {
			$cartBranding = $this->model_extension_oasiscatalog_module_oasis->getCartBranding($cart_item['cart_id']);
			if (!empty($cartBranding) && !empty($cartBranding['branding'])) {
				$branding = $cartBranding['branding'];
				$cart_item_price = $cartBranding['price'] ?? 0;
				if (empty($cart_item_price) || $cartBranding['price_up'] < date('Y-m-d')) {
					$cart_item_price = 0;
					OasisConfig::instance($this->registry, [
						'init' => true
					]);

					$result = Api::brandingCalc($this->prepareBrandingData($branding, $cart_item['quantity']), ['timeout' => 10]);

					if (empty($result['error']) && !empty($result['branding'])) {
						foreach ($result['branding'] as $branding) {
							$cart_item_price += ($branding['main']['price']['client']['total'] ?? 0);
						}
					}

					$this->model_extension_oasiscatalog_module_oasis->upCartBrandingPrice($cart_item['cart_id'], $cart_item_price);
				}

				$price += $cart_item_price;
			}
		}

		if (!empty($price)) {
			$this->load->language(self::ROUTE);

			$totals[] = [
				'extension'  => 'oasiscatalog',
				'code'       => 'oasis_branding',
				'title'      => $this->language->get('text_product_option_branding'),
				'value'      => $price,
				'sort_order' => (int)$this->config->get('total_oasis_branding_sort_order')
			];

			$total += $price;
		}
	}

	private function prepareBrandingData(array $branding, int $quantity): array
	{
		$branding_data = [];
		$item = [
			'quantity' => $quantity,
		];
		foreach ($branding as $branding_item) {
			if (empty($item['productId'])) {
				$item['productId'] = $branding_item['productId'];
				$item['branding'] = [];
			}

			$item['branding'][] = count($branding_data);
			$branding_data[] = array_intersect_key($branding_item, array_flip(['placeId', 'typeId', 'width', 'height']));
		}
		return [
			'items' => [$item],
			'branding' => $branding_data
		];
	}
}