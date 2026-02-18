<?php
#!/usr/bin/php

namespace Opencart\Admin\Controller\Extension\Oasis;

use Exception;
use Opencart\Admin\Controller\Extension\Oasis\Main;
use Opencart\Admin\Controller\Extension\Oasis\Config as OasisConfig;
use \Opencart\System\Engine\Registry;


class Cli {
	private Registry $registry;

	private Main $main;
	private const ROUTE = 'extension/oasiscatalog/module/oasis';

    public static OasisConfig $cf;

	/**
	 * @param $registry
	 * @throws Exception
	 */
	public function __construct(Registry $registry) {
		$this->registry = $registry;
		$this->main = new Main($registry);
	}

	public function runCron($cron_key, $cron_opt = []) {
		$cf = OasisConfig::instance();
		$cf->init();
		if (!$cf->checkCronKey($cron_key)) {
			$cf->log('Error! Invalid --key');
			die('Error! Invalid --key');
		}
		if (!$cf->status) {
			$cf->log('Module disabled');
			die('Module disabled');
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

		if($cron_opt['task'] == 'add_image' || $cron_opt['task'] == 'up_image'){
			$this->addImage([
				'oid' => $cron_opt['oid'] ?? '',
				'is_up' => $cron_opt['task'] == 'up_image'
			]);
		}
		else {
			$cf->lock(\Closure::bind(function() use ($cf, $cron_opt){
				switch ($cron_opt['task']) {
					case 'import':
						if(!$cf->checkPermissionImport()) {
							$cf->log('Import once day');
							die('Import once day');
						}
						$cf->initRelation();
						$this->import();
						break;

					case 'up':
						$this->upStock();
						break;
				}
			}, $this), \Closure::bind(function() use ($cf) {
				$cf->log('Already running');
				die('Already running');
			}, $this));
		}
	}

	/**
	 * Import / update products on schedule
	 */
	public function import()
	{
		set_time_limit(0);
		ini_set('memory_limit', '2G');

		self::$cf->log('Начало обновления товаров');
		try {

			$args = [];
			if (self::$cf->limit > 0) {
				$args['limit'] = self::$cf->limit;
				$args['offset'] = self::$cf->progress['step'] * self::$cf->limit;
			}
			$categories = self::$cf->categories ?: Main::getOasisMainCategories();
			$args['category'] = implode(',', $categories);

			$this->main->cats_oasis = Api::getCategoriesOasis();
			$products = Api::getProductsOasis($args);

			if (self::$cf->is_delete_exclude) {
				$all_oasis_products = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProducts();

				if (!empty($all_oasis_products)) {
					$dbOasisProductIds = array_unique(array_column($all_oasis_products, 'product_id_oasis'));
					$resProducts = API::getProductsOasisOnlyFieldCategories($dbOasisProductIds);

					foreach ($resProducts as $resProduct) {
						if (empty(array_intersect($resProduct->categories, $categories))) {
							$this->main->deleteProduct(strval($resProduct->id));
						}
					}
				}
				unset($all_oasis_products, $dbOasisProductIds, $resProducts, $resProduct);
			}

			$groups = [];
			$progressStep = 0;
			foreach ($products as $product) {
				if (!empty($product->size) && !empty($product->parent_size_id)) {
					$groups[$product->parent_size_id][$product->id] = $product;
				}
				else {
					$groups[$product->id][$product->id] = $product;
				}
				$progressStep++;
			}
			array_walk($groups, fn(&$g) => ksort($g));

			if (self::$cf->limit > 0) {
				self::$cf->progressStart(Api::getStatProducts()->products, $progressStep);
			} else {
				self::$cf->progressStart($progressStep, $progressStep);
			}

			$total = count($groups);
			$count = 0;
			foreach ($groups as $products) {
				$product = reset($products);
				self::$cf->log('Начало обработки модели ' . $product->id);
				$dbGroupProducts = $this->registry->model_extension_oasiscatalog_module_oasis->getGroupOasisProducts($product->id);
				$dbProduct = $dbGroupProducts[0] ?? null;

				if (count($products) === 1) {
					if (count($dbGroupProducts) > 1) {
						foreach ($dbGroupProducts as $dbProduct) {
							$this->main->deleteProduct($dbProduct['product_id_oasis']);
						}
						$dbProduct = null;
					}

					$this->main->checkProduct($product, $dbProduct);
				}
				else {
					if (!empty($dbProduct) && (count($dbGroupProducts) !== count($products) || $dbProduct['product_id_oasis'] !== $product->id)) {
						foreach ($dbGroupProducts as $dbProduct) {
							$this->main->deleteProduct($dbProduct['product_id_oasis']);
						}
						$dbProduct = null;
					}
					$productOption = $this->main->getProductOption($product, $products);
					$this->main->checkProduct($product, $dbProduct, $productOption);
				}

				self::$cf->progressUp(count($products));
				self::$cf->log('Done ' . ++$count . ' from ' . $total);
			}
			self::$cf->progressEnd();
			self::$cf->log('Окончание обновления товаров');
		} catch (Exception $exception) {
			echo $exception->getMessage() . PHP_EOL;
			die();
		}
	}

	/**
	 * update product quantities in stock on schedule
	 */
	public function upStock()
	{
		set_time_limit(0);
		ini_set('memory_limit', '2G');

		try {
			self::$cf->log('Начало обновления остатков');

			$stock = [];
			foreach (Api::getStock() as $item) {
				$stock[$item->id] = $item;
			}

			$oasisProducts = [];
			foreach ($this->registry->model_extension_oasiscatalog_module_oasis->getOasisProducts() as $dbProduct) {
				$oasisProducts[$dbProduct['product_id_oasis']] = $dbProduct;
			}

			foreach ($oasisProducts as $oasisProductId => $dbProduct) {
				$stockItem = $stock[$oasisProductId] ?? null;
				if ($stockItem) {
					$productId = $dbProduct['product_id'];
					$quantity = (int)$stockItem->stock + (int)$stockItem->{"stock-remote"};
					if (empty($dbProduct['option_value_id'])) {
						$this->registry->model_extension_oasiscatalog_module_oasis->upProductQuantity($productId, $quantity);
					} else {
						$this->registry->model_extension_oasiscatalog_module_oasis->upProductOptionValue($dbProduct['option_value_id'], $quantity);
						$options = $this->registry->model_extension_oasiscatalog_module_oasis->getProductOptionValues($productId);
						$this->registry->model_extension_oasiscatalog_module_oasis->upProductQuantity($productId, array_sum(array_column($options, 'quantity')));
					}
				}
				else {
					$this->main->deleteProduct($oasisProductId);
				}
			}
			self::$cf->log('Окончание обновления остатков');
		} catch (Exception $exception) {
			echo $exception->getMessage() . PHP_EOL;
			die();
		}
	}


	public function addImage($opt = [])
	{
		set_time_limit(0);
		ini_set('memory_limit', '2G');

		self::$cf->log('Начало обновления картинок');

		$products = Api::getProductsOasis([
			'fields' => 'id,article,images,updated_at,images_updated_at',
		]);
		$total = count($products);
		$i = 0;
		foreach ($products as $productOasis) {
			// todo: повторяется product_id
			$dbProduct = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProduct($productOasis->id);

			if (!empty($dbProduct) && ($opt['is_up'] || $this->main->getNeedImagesUp($productOasis, $dbProduct))) {
				$productId = $dbProduct['product_id'];
				if (self::$cf->is_cdn_photo) {
					$this->main->updateImageCDN($productOasis);
				}
				else {
					$this->main->deleteImages($this->registry->model_catalog_product->getImages($productId));
					$this->registry->model_catalog_product->deleteImages($productId);

					$categories = $this->registry->model_catalog_product->getCategories($productId);
					$product_images = $this->main->prepareImagesProduct($productOasis->images, $categories);

					$firstImage = empty($product_images) ? '' : $product_images[0]['image'];
					$this->registry->model_extension_oasiscatalog_module_oasis->setProductImage($productId, $firstImage);
					foreach ($product_images as $product_image) {
						$this->registry->model_catalog_product->addImage($productId, $product_image);
					}
				}
			}
			self::$cf->log('Done ' . ++$i . ' from ' . $total);
		}
		$this->registry->cache->delete('product');
		self::$cf->log('Окончание обновления картинок');
	}
}