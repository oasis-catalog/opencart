<?php
#!/usr/bin/php

namespace Opencart\Admin\Controller\Extension\Oasis;

require_once('Api.php');
require_once('Main.php');
require_once('Config.php');

use Exception;
use Opencart\Admin\Controller\Extension\Oasis\Config as OasisConfig;
use \Opencart\System\Engine\Registry;


class Cli {
	private OasisConfig $cf;
	private Registry $registry;

	private Main $main;
	private array $products = [];
	private const ROUTE = 'extension/oasiscatalog/module/oasis';

	/**
	 * @param $registry
	 * @throws Exception
	 */
	public function __construct(Registry $registry)
	{
		$this->registry = $registry;
		$this->main = new Main($registry);
		$this->cf = OasisConfig::instance();
	}

	public function runCron($cron_key, $cron_up)
	{
		$this->cf->lock(\Closure::bind(function() use ($cron_key, $cron_up) {
			$this->cf->init();
			$this->cf->initRelation();

			if (!$this->cf->checkCronKey($cron_key)) {
				$this->cf->log('Error! Invalid --key');
				die('Error! Invalid --key');
			}

			if (!$this->cf->status) {
				$this->cf->log('Module disabled');
				die('Module disabled');
			}

			if (!$cron_up && !$this->cf->checkPermissionImport()) {
				$this->cf->log('Import once day');
				die('Import once day');
			}

			$this->registry->load->model(self::ROUTE);
			$this->registry->load->language(self::ROUTE);
			$this->registry->load->model('catalog/attribute');
			$this->registry->load->model('catalog/attribute_group');
			$this->registry->load->model('catalog/category');
			$this->registry->load->model('catalog/option');
			$this->registry->load->model('catalog/product');
			$this->registry->load->model('catalog/manufacturer');
			$this->registry->load->model('localisation/language');
			$this->registry->load->model('setting/store');
			$this->registry->load->model('design/seo_url');

			if ($cron_up) {
				$this->upStock();
			} else {
				$this->upProduct();
			}
		}, $this), \Closure::bind(function() {
			$this->cf->log('Already running');
			die('Already running');
		}, $this));
	}

	/**
	 * Import / update products on schedule
	 */
	public function upProduct(): void
	{
		set_time_limit(0);
		ini_set('memory_limit', '2G');

		$this->cf->log( 'Начало обновления товаров' );

		$args = [
			'fieldset' => 		'full',
			'currency' => 		$this->cf->currency,
			'no_vat' => 		$this->cf->is_no_vat,
			'not_on_order' =>	$this->cf->is_not_on_order,
			'price_from' =>		$this->cf->price_from,
			'price_to' =>		$this->cf->price_to,
			'rating' =>			$this->cf->rating,
			'moscow' =>			$this->cf->is_wh_moscow,
			'europe' =>			$this->cf->is_wh_europe,
			'remote' =>			$this->cf->is_wh_remote,
			'category' =>		$this->cf->categories,
		];
		foreach ($args as $key => $value) {
			if (empty($value)) {
				unset($args[$key]);
			}
		}

		if ($this->cf->limit > 0) {
			$args['limit'] = $this->cf->limit;
			$args['offset'] = $this->cf->progress['step'] * $this->cf->limit;
		}

		try {
			$cats_oasis = Api::getCategoriesOasis(['fields' => 'id,parent_id,root,level,slug,name,path']);
			$this->main->cats_oasis = $cats_oasis;

			if (empty($args['category'])) {
				$ids = [];
				foreach ($cats_oasis as $cat) {
					if ($cat->level === 1) {
						$ids[] = $cat->id;
					}
				}
				$args['category'] = $ids;
				unset($cat, $ids);
			}

			$selected_category = $args['category'];
			$args['category'] = implode(',', $args['category']);

			$this->products = Api::getProductsOasis($args);

			if ($this->cf->is_delete_exclude) {
				$all_oasis_products = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProducts();

				if (!empty($all_oasis_products)) {
					$dbOasisProductIds = array_unique(array_column($all_oasis_products, 'product_id_oasis'));
					$resProducts = API::getProductsOasisOnlyFieldCategories($dbOasisProductIds);

					foreach ($resProducts as $resProduct) {
						if (empty(array_intersect($resProduct->categories, $selected_category))) {
							$this->main->checkDeleteProduct(strval($resProduct->id));
						}
					}
				}
				unset($all_oasis_products, $dbOasisProductIds, $resProducts, $resProduct);
			}

			$stats = Api::getStatProducts($cats_oasis);
			$totalProduct = count($this->products);
			$this->cf->progressStart($stats->products, $totalProduct);

			if ($this->products) {
				foreach ($this->products as $product) {
					$this->cf->log('Начало обработки модели '.$this->cf->progress['step_total'].'-'.($this->cf->progress['step_item'] + 1));
					$this->product($product, $args);
					$this->cf->progressUp();
				}
			}
			$this->cf->progressEnd();
			$this->cf->log('Окончание обновления товаров');
		} catch (Exception $exception) {
			echo $exception->getMessage() . PHP_EOL;
			die();
		}
	}

	

	/**
	 * @param object $product
	 * @param array $args
	 * @param array $data
	 * @return int|null
	 * @throws Exception
	 */
	private function product(object $product, array $args): ?int
	{
		$result = null;

		$data = [];
		if($this->cf->is_no_vat){
			$data['tax_class_id'] = $this->cf->tax_class_id ?? 0;
		}

		if (!empty($product->size) && !is_null($product->parent_size_id)) {
			$option = $this->main->getOption($this->main->var_size, $product->size, intval($product->total_stock));
			$data['option'] = $option['option']['name'];
			$data['product_option'] = $this->main->setOption($option);

			if ($product->parent_size_id === $product->id) {
				$result = $this->main->checkProduct($data, $product);
			} else {
				$args['ids'] = [
					'id' => $product->parent_size_id,
				];

				$parent_product = [];
				foreach ($this->products as $key => $item) {
					if ($item->id === $args['ids']['id']) {
						$parent_product = $this->products[$key];
						break;
					}
				}

				if (!$parent_product) {
					$parent_product_oasis = Api::getProductOasis($args);
					$parent_product = $parent_product_oasis ? array_shift($parent_product_oasis) : false;
				}

				if (!empty($parent_product)) {
					$product_oc = $this->registry->model_catalog_product->getProducts(['filter_model' => $parent_product->article]);

					if (!$product_oc) {
						$parent_id = $this->product($parent_product, $args);
						$product_oc[] = $this->registry->model_catalog_product->getProduct($parent_id);
					}

					$this->main->editProduct($product_oc[0], $product, $data['product_option']);
				} else {
					$this->cf->log('OAId='.$id.', parent_id = '.$args['ids']['id'].' | Error. Product ID not found!');
				}
			}
		} else {
			$this->main->checkProduct($data, $product);
		}

		return $result;
	}

	/**
	 * update product quantities in stock on schedule
	 */
	public function upStock(): void
	{
		set_time_limit(0);
		ini_set('memory_limit', '2G');

		try {
			$this->cf->log('Начало обновления остатков');

			$stock = Api::getStock();
			$arrOasis = [];

			foreach ($stock as $key => $item) {
				$arrOasis[] = $item->id;
				$oasisProduct = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProduct($item->id);

				if ($oasisProduct && (int)$oasisProduct['rating'] !== 5) {
					if ((int)$oasisProduct['option_value_id'] === 0) {
						$this->registry->model_extension_oasiscatalog_module_oasis->upProductQuantity($oasisProduct['product_id'], $item->stock);
					} else {
						$this->registry->model_extension_oasiscatalog_module_oasis->upProductOptionValue($oasisProduct['option_value_id'], $item->stock);
						$product_options = $this->registry->model_extension_oasiscatalog_module_oasis->getProductOptionValues($oasisProduct['product_id']);

						if (array_search(1000000, array_column($product_options, 'quantity')) === false) {
							$this->registry->model_extension_oasiscatalog_module_oasis->upProductQuantity($oasisProduct['product_id'], array_sum(array_column($product_options, 'quantity')));
						}
					}
				}
			}
			unset($item, $oasisProduct, $product_options);

			$oasisProducts = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProducts();
			$arrProduct = [];

			foreach ($oasisProducts as $product) {
				$arrProduct[] = $product['product_id_oasis'];
			}
			unset($product);

			$array_diff = array_diff($arrProduct, $arrOasis);

			if ($array_diff) {
				foreach ($array_diff as $key => $value) {
					if ((int)$oasisProducts[$key]['option_value_id'] === 0) {
						$this->registry->model_extension_oasiscatalog_module_oasis->disableProduct($oasisProducts[$key]['product_id']);
					} else {
						$this->registry->model_extension_oasiscatalog_module_oasis->upProductOptionValue($oasisProducts[$key]['option_value_id'], 0);
						$product_options = $this->registry->model_extension_oasiscatalog_module_oasis->getProductOptionValues($oasisProducts[$key]['product_id']);

						if (array_sum(array_column($product_options, 'quantity')) === 0) {
							$this->registry->model_extension_oasiscatalog_module_oasis->disableProduct($oasisProducts[$key]['product_id']);
						}
					}
				}
				unset($key, $value);
			}

			$this->cf->log('Окончание обновления остатков');
		} catch (Exception $exception) {
			echo $exception->getMessage() . PHP_EOL;
			die();
		}
	}
}