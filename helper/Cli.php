<?php
#!/usr/bin/php

namespace Opencart\Admin\Controller\Extension\Oasis;

require_once('Api.php');
require_once('Main.php');

use Exception;
use Opencart\System\Engine\Controller;

/**
 * @property object $model_extension_oasiscatalog_module_oasis
 * @property object $load
 * @property object $config
 * @property object $model_catalog_product
 */
class Cli extends Controller
{
    private Main $main;
    public array $cat_oasis = [];
    public bool $delete_exclude = false;
    public array $selected_category = [];
    private array $products = [];
    private const ROUTE = 'extension/oasiscatalog/module/oasis';

    /**
     * @param $registry
     * @throws Exception
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->main = new Main($registry);
        $this->index();
    }

    public function index(): void
    {
        try {
            $dir_lock = Main::getOrCreateDir(DIR_STORAGE . 'process_lock');

            $lock = fopen($dir_lock . '/start.lock', 'w');
            if (!($lock && flock($lock, LOCK_EX | LOCK_NB))) {
                throw new Exception('Already running oasis');
            }

            $this->load->model(self::ROUTE);
            $this->load->model('catalog/attribute');
            $this->load->model('catalog/attribute_group');
            $this->load->model('catalog/category');
            $this->load->model('catalog/option');
            $this->load->model('catalog/product');
            $this->load->model('catalog/manufacturer');
            $this->load->model('localisation/language');
            $this->load->model('setting/store');
            $this->load->model('design/seo_url');

            if (CRON_UP) {
                $this->cronUpStock();
            } else {
                $this->cronUpProduct();
            }

        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            exit();
        }
    }

    /**
     * Import / update products on schedule
     */
    public function cronUpProduct(): void
    {
        $this->model_extension_oasiscatalog_module_oasis->deleteOption(0, 'oasiscatalog', 'progress_tmp');

        $args = [
            'fieldset' => 'full',
        ];
        $args += $this->config->get('oasiscatalog_args');
        $data = [];
        $limit = !empty($args['limit']) ? (int)$args['limit'] : 0;
        $step = (int)$this->config->get('oasiscatalog_step');
        $this->delete_exclude = (bool)$this->config->get('oasiscatalog_delete_exclude');

        if ($limit > 0) {
            $args['limit'] = $limit;
            $args['offset'] = $step * $limit;
        } else {
            unset($args['limit']);
        }

        if ($args['no_vat'] === '1') {
            $data['tax_class_id'] = $this->config->get('oasiscatalog_tax_class_id');
        } else {
            $data['tax_class_id'] = 0;
        }

        $args['category'] = $this->config->get('oasiscatalog_category');

        try {
            set_time_limit(0);
            ini_set('memory_limit', '2G');

            $this->cat_oasis = Api::getCategoriesOasis(['fields' => 'id,parent_id,root,level,slug,name,path']);

            if (!$args['category']) {
                $ids = [];
                foreach ($this->cat_oasis as $cat) {
                    if ($cat->level === 1) {
                        $ids[] = $cat->id;
                    }
                }
                $args['category'] = implode(',', $ids);
                unset($cat, $ids);
            }

            $this->selected_category = explode(',', $args['category']);
            $this->products = Api::getProductsOasis($args);
            $stat = Api::getStatProducts($this->config);
            $this->main->dataThis($this->cat_oasis);

            if ($this->delete_exclude) {
                $all_oasis_products = $this->model_extension_oasiscatalog_module_oasis->getOasisProducts();

                if (!empty($all_oasis_products)) {
                    $dbOasisProductIds = array_unique(array_column($all_oasis_products, 'product_id_oasis'));
                    $resProducts = API::getProductsOasisOnlyFieldCategories($dbOasisProductIds);

                    foreach ($resProducts as $resProduct) {
                        if (empty(array_intersect($resProduct->categories, $this->selected_category))) {
                            $this->main->checkDeleteProduct(strval($resProduct->id));
                        }
                    }
                }
                unset($all_oasis_products, $dbOasisProductIds, $resProducts, $resProduct);
            }

            $progress_data = $this->config->get('progress');
            $tmpBar = [
                'item'  => intval($progress_data['item'] ?? 0),
                'total' => $stat->products,
            ];

            if ($limit > 0) {
                $tmpBar['step_item'] = 0;
                $tmpBar['step_total'] = count($this->products);
            }

            if ($this->products) {
                $nextStep = ++$step;
                $totalProduct = count($this->products);
                $i = 1;

                foreach ($this->products as $product) {
                    $this->main->logCounter($totalProduct . '-' . $i);
                    $this->product($product, $args, $data);
                    $i++;

                    $tmpBar['item']++;

                    if (!empty($limit)) {
                        $tmpBar['step_item']++;
                    }
                    $this->model_extension_oasiscatalog_module_oasis->setOption(0, 'oasiscatalog', 'progress_tmp', $tmpBar);
                }
                unset($totalProduct, $i);
            } else {
                $nextStep = 0;
                $tmpBar['item'] = 0;
            }

            if (!empty($limit)) {
                $this->model_extension_oasiscatalog_module_oasis->setOption(0, 'oasiscatalog', 'oasiscatalog_step', $nextStep);
            } else {
                $tmpBar['item'] = 0;
            }

            $this->model_extension_oasiscatalog_module_oasis->deleteOption(0, 'oasiscatalog', 'progress_tmp');
            $this->model_extension_oasiscatalog_module_oasis->setOption(0, 'oasiscatalog', 'progress', [
                'item'       => $tmpBar['item'],
                'total'      => $stat->products,
                'step_item'  => 0,
                'step_total' => 0,
            ]);
            $this->model_extension_oasiscatalog_module_oasis->setOption(0, 'oasiscatalog', 'oasiscatalog_progress_date', date('Y-m-d H:i:s'));
        } catch (Exception $exception) {
            echo $exception->getMessage() . PHP_EOL;
            die();
        }
    }

    /**
     * update product quantities in stock on schedule
     */
    public function cronUpStock(): void
    {
        try {
            set_time_limit(0);
            $stock = Api::getStock();
            $arrOasis = [];

            foreach ($stock as $key => $item) {
                $arrOasis[] = $item->id;
                $oasisProduct = $this->model_extension_oasiscatalog_module_oasis->getOasisProduct($item->id);

                if ($oasisProduct && (int)$oasisProduct['rating'] !== 5) {
                    if ((int)$oasisProduct['option_value_id'] === 0) {
                        $this->model_extension_oasiscatalog_module_oasis->upProductQuantity($oasisProduct['product_id'], $item->stock);
                    } else {
                        $this->model_extension_oasiscatalog_module_oasis->upProductOptionValue($oasisProduct['option_value_id'], $item->stock);
                        $product_options = $this->model_extension_oasiscatalog_module_oasis->getProductOptionValues($oasisProduct['product_id']);

                        if (array_search(1000000, array_column($product_options, 'quantity')) === false) {
                            $this->model_extension_oasiscatalog_module_oasis->upProductQuantity($oasisProduct['product_id'], array_sum(array_column($product_options, 'quantity')));
                        }
                    }
                }
            }
            unset($item, $oasisProduct, $product_options);

            $oasisProducts = $this->model_extension_oasiscatalog_module_oasis->getOasisProducts();
            $arrProduct = [];

            foreach ($oasisProducts as $product) {
                $arrProduct[] = $product['product_id_oasis'];
            }
            unset($product);

            $array_diff = array_diff($arrProduct, $arrOasis);

            if ($array_diff) {
                foreach ($array_diff as $key => $value) {
                    if ((int)$oasisProducts[$key]['option_value_id'] === 0) {
                        $this->model_extension_oasiscatalog_module_oasis->disableProduct($oasisProducts[$key]['product_id']);
                    } else {
                        $this->model_extension_oasiscatalog_module_oasis->upProductOptionValue($oasisProducts[$key]['option_value_id'], 0);
                        $product_options = $this->model_extension_oasiscatalog_module_oasis->getProductOptionValues($oasisProducts[$key]['product_id']);

                        if (array_sum(array_column($product_options, 'quantity')) === 0) {
                            $this->model_extension_oasiscatalog_module_oasis->disableProduct($oasisProducts[$key]['product_id']);
                        }
                    }
                }
                unset($key, $value);
            }
            $this->main->saveToLog('null', 'Stock updated');
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
    public function product(object $product, array $args, array $data = []): ?int
    {
        $result = null;

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
                    $product_oc = $this->model_catalog_product->getProducts(['filter_model' => $parent_product->article]);

                    if (!$product_oc) {
                        $parent_id = $this->product($parent_product, $args, $data);
                        $product_oc[] = $this->model_catalog_product->getProduct($parent_id);
                    }

                    $this->main->editProduct($product_oc[0], $product, $data['product_option']);
                } else {
                    $this->main->saveToLog($product->id, 'parent_id = ' . $args['ids']['id'] . ' | Error. Product ID not found!', 'oasisError');
                }
                unset($product_oc, $parent_product_oasis);
            }
        } else {
            $this->main->checkProduct($data, $product);
        }

        return $result;
    }
}
