<?php
/**
 * Class ControllerExtensionModuleOasiscatalog
 */

class ControllerExtensionModuleOasiscatalog extends Controller
{
    private $error = [];
    private $cat_oasis = [];
    private $mf_oasis = [];
    private $products = [];
    private $var_size = 'Размер';
    private $treeCats = '';
    private const ROUTE = 'extension/module/oasiscatalog';
    private const API_URL = 'https://api.oasiscatalog.com/';
    private const API_V4 = 'v4/';
    private const API_V3 = 'v3/';
    private const API_CURRENCIES = 'currencies';
    private const API_CATEGORIES = 'categories';
    private const API_PRODUCTS = 'products';
    private const API_BRANDS = 'brands';
    private const API_STOCK = 'stock';
    private const API_CAT_FIELDS = 'id,parent_id,root,level,slug,name,path';

    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->load->model('setting/setting');

        define('API_KEY', $this->config->get('oasiscatalog_api_key'));
        define('CRON_KEY', md5($this->config->get('oasiscatalog_api_key')));
    }

    /**
     * @throws Exception
     */
    public function index()
    {
        $this->load->language(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $post_data['oasiscatalog_status'] = $this->request->post['oasiscatalog_status'] ?? 0;
            $post_data['oasiscatalog_api_key'] = $this->request->post['oasiscatalog_api_key'] ?? '';
            $post_data['oasiscatalog_user_id'] = $this->request->post['oasiscatalog_user_id'] ?? '';
            $post_data['oasiscatalog_args'] = [
                'currency' => $this->request->post['currency'] ?? 'rub',
                'no_vat' => $this->request->post['no_vat'] ?? '0',
            ];

            if (isset($this->request->post['not_on_order']) && $this->request->post['not_on_order'] !== '') {
                $post_data['oasiscatalog_args']['not_on_order'] = $this->request->post['not_on_order'];
            }

            if (isset($this->request->post['price_from']) && $this->request->post['price_from'] !== '') {
                $post_data['oasiscatalog_args']['price_from'] = $this->request->post['price_from'];
            }

            if (isset($this->request->post['price_to']) && $this->request->post['price_to'] !== '') {
                $post_data['oasiscatalog_args']['price_to'] = $this->request->post['price_to'];
            }

            if (isset($this->request->post['rating']) && $this->request->post['rating'] !== '') {
                $post_data['oasiscatalog_args']['rating'] = $this->request->post['rating'];
            }

            if (isset($this->request->post['warehouse_moscow']) && $this->request->post['warehouse_moscow'] !== '') {
                $post_data['oasiscatalog_args']['warehouse_moscow'] = $this->request->post['warehouse_moscow'];
            }

            if (isset($this->request->post['warehouse_europe']) && $this->request->post['warehouse_europe'] !== '') {
                $post_data['oasiscatalog_args']['warehouse_europe'] = $this->request->post['warehouse_europe'];
            }

            if (isset($this->request->post['remote_warehouse']) && $this->request->post['remote_warehouse'] !== '') {
                $post_data['oasiscatalog_args']['remote_warehouse'] = $this->request->post['remote_warehouse'];
            }

            if (isset($this->request->post['category']) && $this->request->post['category'] !== '') {
                $post_data['oasiscatalog_category'] = implode(',', $this->request->post['category']);
            } else {
                $post_data['oasiscatalog_category'] = [];
            }

            if (isset($this->request->post['tax_class_id']) && $this->request->post['tax_class_id'] !== '') {
                $post_data['oasiscatalog_tax_class_id'] = (int)$this->request->post['tax_class_id'];
            } else {
                $post_data['oasiscatalog_tax_class_id'] = 0;
            }

            $this->model_setting_setting->editSetting('oasiscatalog', $post_data);

            $this->cache->delete('oasiscatalog');
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token'], true),
        ];

        $data['action'] = $this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['user_token'] = $this->session->data['user_token'];

        $data['status'] = $this->config->get('oasiscatalog_status');
        $data['api_key'] = API_KEY;
        $data['api_key_status'] = false;
        $data['user_id'] = $this->config->get('oasiscatalog_user_id');
        $data['cron_product'] = 'wget "' . HTTP_SERVER . 'index.php?route=extension/module/oasiscatalog/cronUpProduct&key=' . CRON_KEY . '"';
        $data['cron_stock'] = 'wget "' . HTTP_SERVER . 'index.php?route=extension/module/oasiscatalog/cronUpStock&key=' . CRON_KEY . '"';

        if ($data['api_key']) {
            $currencies = $this->getCurrenciesOasis();
            $data['api_key_status'] = (bool)$currencies;

            if ($data['api_key_status']) {
                $args = $this->config->get('oasiscatalog_args');
                if ($args) {
                    $data += $args;
                }
                $data['tax_class_id'] = $this->config->get('oasiscatalog_tax_class_id');

                $cats = $this->config->get('oasiscatalog_category');

                if ($cats) {
                    $data['category'] = '[' . $this->config->get('oasiscatalog_category') . ']';
                } else {
                    $data['category'] = '';
                }

                $data['currencies'] = [];

                foreach ($currencies as $currency) {
                    $data['currencies'][$currency->code] = $currency->full_name;
                }
                unset($currency);

                $this->load->model('localisation/tax_class');

                $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

                $categories = $this->getCategoriesOasis(['fields' => self::API_CAT_FIELDS]);

                $arr_cat = [];
                foreach ($categories as $item) {
                    if (empty($arr_cat[(int)$item->parent_id])) {
                        $arr_cat[(int)$item->parent_id] = [];
                    }
                    $arr_cat[(int)$item->parent_id][] = (array)$item;
                }
                $this->buildTreeCats($arr_cat);
                unset($arr_cat, $item);

                $data['categories'] = $this->treeCats;

            } else {
                $data['error_warning'] = $this->language->get('error_api_key');
            }
        } else {
            $data['error_warning'] = $this->language->get('error_api_access');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view(self::ROUTE, $data));
    }

    /**
     * @throws Exception
     */
    public function import()
    {
        $json = [];

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $this->load->language(self::ROUTE);

            $args = [
                'currency' => $this->request->post['currency'] ?? 'rub',
                'no_vat' => $this->request->post['no_vat'] ?? '0',
                'fieldset' => 'full',
            ];

            if (isset($this->request->post['not_on_order']) && $this->request->post['not_on_order'] !== '') {
                $args['not_on_order'] = $this->request->post['not_on_order'];
            }

            if (isset($this->request->post['price_from']) && $this->request->post['price_from'] !== '') {
                $args['price_from'] = $this->request->post['price_from'];
            }

            if (isset($this->request->post['price_to']) && $this->request->post['price_to'] !== '') {
                $args['price_to'] = $this->request->post['price_to'];
            }

            if (isset($this->request->post['rating']) && $this->request->post['rating'] !== '') {
                $args['rating'] = $this->request->post['rating'];
            }

            if (isset($this->request->post['warehouse_moscow']) && $this->request->post['warehouse_moscow'] !== '') {
                $args['warehouse_moscow'] = $this->request->post['warehouse_moscow'];
            }

            if (isset($this->request->post['warehouse_europe']) && $this->request->post['warehouse_europe'] !== '') {
                $args['warehouse_europe'] = $this->request->post['warehouse_europe'];
            }

            if (isset($this->request->post['remote_warehouse']) && $this->request->post['remote_warehouse'] !== '') {
                $args['remote_warehouse'] = $this->request->post['remote_warehouse'];
            }

            if (isset($this->request->post['category']) && $this->request->post['category'] !== '') {
                $args['category'] = implode(',', $this->request->post['category']);
            } else {
                $args['category'] = [];
            }

            if (isset($this->request->post['tax_class']) && $this->request->post['tax_class'] !== '') {
                $data['tax_class_id'] = (int)$this->request->post['tax_class'];
            } else {
                $data['tax_class_id'] = 0;
            }

            try {
                set_time_limit(0);
                ini_set('memory_limit', '2G');
                ini_set('mysql.connect_timeout', '120');
                $this->cat_oasis = $this->getCategoriesOasis(['fields' => self::API_CAT_FIELDS]);

                if (empty($args['category'])) {
                    $ids = [];
                    foreach ($this->cat_oasis as $cat) {
                        if ($cat->level === 1) {
                            $ids[] = $cat->id;
                        }
                    }
                    $args['category'] = implode(',', $ids);
                    unset($cat, $ids);
                }

                $this->products = $this->curl_query(self::API_V4, self::API_PRODUCTS, $args);
                $this->mf_oasis = $this->getBrandsOasis();

                if ($this->products) {
                    $i = 0;
                    foreach ($this->products as $product) {
                        $this->saveToLog($product->id, 'Iteration - ' . $i);
                        $this->product($product, $args, $data);
                        $i++;
                    }
                    $json['text'] = 'All products updated';
                } else {
                    $json['text'] = 'Not products';
                }
            } catch (\Exception $exception) {
                return;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Import / update products on schedule
     */
    public function cronUpProduct()
    {
        if (!isset($_GET['key']) || $_GET['key'] !== CRON_KEY) {
            return;
        }

        $args = [
            'fieldset' => 'full',
        ];
        $args += $this->config->get('oasiscatalog_args');
        $data = [];

        if ($args['no_vat'] === '1') {
            $data['tax_class_id'] = $this->config->get('oasiscatalog_tax_class_id');
        } else {
            $data['tax_class_id'] = 0;
        }

        $args['category'] = $this->config->get('oasiscatalog_category');

        try {
            set_time_limit(0);
            ini_set('memory_limit', '2G');
            ini_set('mysql.connect_timeout', '120');
            $this->cat_oasis = $this->getCategoriesOasis(['fields' => self::API_CAT_FIELDS]);

            if (is_null($args['category']) || $args['category'] === '') {
                $ids = [];
                foreach ($this->cat_oasis as $cat) {
                    if ($cat->level === 1) {
                        $ids[] = $cat->id;
                    }
                }
                $args['category'] = implode(',', $ids);
                unset($cat, $ids);
            }

            $this->products = $this->curl_query(self::API_V4, self::API_PRODUCTS, $args);
            $this->mf_oasis = $this->getBrandsOasis();

            if ($this->products) {
                foreach ($this->products as $product) {
                    $this->product($product, $args, $data);
                }
            }
        } catch (\Exception $exception) {
            return;
        }
    }

    /**
     * Import / update product quantities in stock on schedule
     */
    public function cronUpStock()
    {
        if (!isset($_GET['key']) || $_GET['key'] !== CRON_KEY) {
            return;
        }

        $this->load->model('extension/module/oasiscatalog');

        try {
            set_time_limit(0);
            $stock = $this->curl_query(self::API_V4, self::API_STOCK, ['fields' => 'id,stock']);
            $arrOasis = [];

            foreach ($stock as $key => $item) {
                $arrOasis[] = $item->id;
                $oasisProduct = $this->model_extension_module_oasiscatalog->getOasisProduct($item->id);

                if ($oasisProduct && (int)$oasisProduct['rating'] !== 5) {
                    if ((int)$oasisProduct['option_value_id'] === 0) {
                        $this->model_extension_module_oasiscatalog->upProductQuantity($oasisProduct['product_id'], $item->stock);
                    } else {
                        $this->model_extension_module_oasiscatalog->upProductOptionValue($oasisProduct['option_value_id'], $item->stock);
                        $product_options = $this->model_extension_module_oasiscatalog->getProductOptionValues($oasisProduct['product_id']);

                        if (array_search(1000000, array_column($product_options, 'quantity')) === false) {
                            $this->model_extension_module_oasiscatalog->upProductQuantity($oasisProduct['product_id'], array_sum(array_column($product_options, 'quantity')));
                        }
                    }
                }
            }
            unset($item, $oasisProduct, $product_options);

            $oasisProducts = $this->model_extension_module_oasiscatalog->getOasisProducts();

            $arrProduct = [];
            foreach ($oasisProducts as $product) {
                $arrProduct[] = $product['product_id_oasis'];
            }
            unset($product);

            $array_diff = array_diff($arrProduct, $arrOasis);

            if ($array_diff) {
                foreach ($array_diff as $key => $value) {
                    if ((int)$oasisProducts[$key]['option_value_id'] === 0) {
                        $this->model_extension_module_oasiscatalog->disableProduct($oasisProducts[$key]['product_id']);
                    } else {
                        $this->model_extension_module_oasiscatalog->upProductOptionValue($oasisProducts[$key]['option_value_id'], 0);
                        $product_options = $this->model_extension_module_oasiscatalog->getProductOptionValues($oasisProducts[$key]['product_id']);

                        if (array_sum(array_column($product_options, 'quantity')) === 0) {
                            $this->model_extension_module_oasiscatalog->disableProduct($oasisProducts[$key]['product_id']);
                        }
                    }
                }
                unset($key, $value);
            }
            $this->saveToLog('cron', 'Stock updated');
        } catch (\Exception $exception) {
            return;
        }
    }

    /**
     * @param       $product
     * @param       $args
     * @param array $data
     * @return int|null
     * @throws Exception
     */
    public function product($product, $args, array $data = []): ?int
    {
        $this->load->model('catalog/product');

        $result = null;

        if (!is_null($product->parent_size_id)) {

            $option = $this->getOption($this->var_size, $product->size, $product->total_stock);

            $data['option'] = $option['option']['name'];
            $data['product_option'] = $this->setOption($option);

            if ($product->parent_size_id === $product->id) {
                $result = $this->checkProduct($data, $product);
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
                    $parent_product_oasis = $this->getProductOasis($args);
                    $parent_product = $parent_product_oasis ? array_shift($parent_product_oasis) : false;
                }

                if (!empty($parent_product)) {
                    $product_oc = $this->model_catalog_product->getProducts(['filter_model' => $parent_product->article]);

                    if (!$product_oc) {
                        $parent_id = $this->product($parent_product, $args, $data);
                        $product_oc[] = $this->model_catalog_product->getProduct($parent_id);
                    }

                    $this->editProduct($product_oc[0], $product, $data['product_option']);
                } else {
                    $this->saveToLog($product->id, 'parent_id = ' . $args['ids']['id'] . ' | Error. Product ID not found!');
                }
                unset($product_oc, $parent_product_oasis);
            }
        } else {
            $this->checkProduct($data, $product);
        }

        return $result;
    }

    /**
     * @param $data
     * @param $product
     * @return int
     * @throws Exception
     */
    public function checkProduct($data, $product): int
    {
        $product_oc = $this->model_catalog_product->getProducts(['filter_model' => $product->article]);

        if (!$product_oc) {
            $product_id = $this->addProduct($data, $product);

            $this->saveToLog($product->id, 'Product add');
        } else {
            $this->editProduct($product_oc[0], $product, $data['product_option'] ?? []);
            $product_id = $product_oc[0]['product_id'];
        }

        return $product_id;
    }

    /**
     * @param       $product_info
     * @param       $product_oasis
     * @param array $product_option
     * @return bool
     * @throws Exception
     */
    public function editProduct($product_info, $product_oasis, array $product_option = []): bool
    {
        $this->load->model('extension/module/oasiscatalog');

        $date_modified = $this->model_extension_module_oasiscatalog->getOasisProductDateModified($product_oasis->id);

        /*if ($date_modified && strtotime($product_oasis->updated_at) < strtotime($date_modified['option_date_modified'])) {
            $this->saveToLog($product_oasis->id, 'Product not updated');

            return false;
        }*/

        $this->load->language(self::ROUTE);
        $this->load->model('catalog/product');
        $this->load->model('catalog/manufacturer');
        $this->load->model('catalog/category');
        $this->load->model('catalog/attribute');
        $this->load->model('catalog/option');

        $data = $product_info;

        $data['product_option'] = $this->model_catalog_product->getProductOptions($product_info['product_id']);

        $option_value_id = 0;
        if ($product_option) {
            $option_value_id = $product_option[0]['product_option_value'][0]['option_value_id'];

            if ((float)$data['price'] < (float)$product_oasis->price) {
                $product_option[0]['product_option_value'][0]['price'] = (float)$product_oasis->price - (float)$data['price'];
            } elseif ((float)$data['price'] > (float)$product_oasis->price) {
                $product_option[0]['product_option_value'][0]['price'] = (float)$data['price'] - (float)$product_oasis->price;
                $product_option[0]['product_option_value'][0]['price_prefix'] = '-';
            }

            if ($data['product_option']) {
                foreach ($data['product_option'][0]['product_option_value'] as $key => $value) {
                    if ($value['option_value_id'] === $product_option[0]['product_option_value'][0]['option_value_id']) {
                        $data['product_option'][0]['product_option_value'][$key]['quantity'] = $product_option[0]['product_option_value'][0]['quantity'];
                    }
                }
                unset($key, $value);

                foreach ($data['product_option'][0]['product_option_value'] as $key => $value) {
                    if ($value['option_value_id'] === $product_option[0]['product_option_value'][0]['option_value_id']) {
                        $data['product_option'][0]['product_option_value'][$key] = $product_option[0]['product_option_value'][0];
                    }
                }

                $key_option = array_search($product_option[0]['product_option_value'][0]['option_value_id'], array_column($data['product_option'][0]['product_option_value'], 'option_value_id'));

                if ($key_option === false) {
                    $data['product_option'][0]['product_option_value'][] = $product_option[0]['product_option_value'][0];
                }
                unset($key_option);
            } else {
                $data['product_option'] = $product_option;
            }
        }

        $manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($product_info['manufacturer_id']);

        if ($manufacturer_info) {
            $data['manufacturer'] = $manufacturer_info['name'];
        }

        $data['product_category'] = $this->getArrCategories($product_oasis->full_categories);

        $images = $this->model_catalog_product->getProductImages($product_info['product_id']);

        $data['product_image'] = [];

        foreach ($images as $key => $value) {
            $data['product_image'][$key] = [
                'image' => $value['image'],
                'sort_order' => $value['sort_order'],
            ];
        }
        unset($key, $value);

        $product_data = $this->model_extension_module_oasiscatalog->getOasisProduct($product_oasis->group_id);
        if ($product_data) {
            $product_related = $this->model_catalog_product->getProductRelated($product_data['product_id']);

            if ($product_oasis->group_id !== $product_oasis->id && $product_info['product_id'] !== $product_data['product_id']) {
                $product_related[] = $product_data['product_id'];
            }
            $data['product_related'] = $product_related;
        }

        $arr_product = $this->setProduct($data, $product_oasis, $option_value_id);
        $this->model_catalog_product->editProduct($product_info['product_id'], $arr_product);

        if ($product_option) {
            $product_option_value = $this->model_extension_module_oasiscatalog->getProductOptionValueId($product_info['product_id'], $product_option[0]['product_option_value'][0]['option_value_id']);
        }

        if (empty($date_modified)) {
            $args = [
                'product_id_oasis' => $product_oasis->id,
                'rating' => $product_oasis->rating,
                'option_value_id' => $product_option_value['product_option_value_id'] ?? '',
                'product_id' => $product_info['product_id'],
            ];
            $this->model_extension_module_oasiscatalog->addOasisProduct($args);
        } else {
            $args = [
                'rating' => $product_oasis->rating,
                'option_value_id' => $product_option_value['product_option_value_id'] ?? '',
                'product_id' => $product_info['product_id'],
            ];
            $this->model_extension_module_oasiscatalog->editOasisProduct($product_oasis->id, $args);
        }

        $this->saveToLog($product_oasis->id, 'Product updated');

        return true;
    }

    /**
     * @param       $data
     * @param       $product
     * @return integer
     * @throws Exception
     */
    public function addProduct($data, $product): int
    {
        $this->load->model('catalog/product');

        $data['product_category'] = $this->getArrCategories($product->full_categories);

        if (!is_null($product->brand_id)) {
            $data['manufacturer_id'] = $this->addBrand($product->brand_id);
        }

        if (is_array($product->images)) {
            foreach ($product->images as $image) {
                if (isset($image->superbig)) {
                    $data_img = [
                        'folder_name' => 'catalog/oasis/products/' . end($data['product_category']),
                        'img_url' => $image->superbig,
                        'count' => 0,
                    ];

                    $data['product_image'][] = [
                        'image' => $this->saveImg($data_img),
                        'sort_order' => '',
                    ];
                }
            }
            unset($image);
        }

        if (isset($data['product_image'])) {
            $data['image'] = $data['product_image'][0]['image'];
        }

        $arr_product = $this->setProduct($data, $product);
        $product_id = $this->model_catalog_product->addProduct($arr_product);

        $this->load->model('extension/module/oasiscatalog');
        if (!empty($data['product_option'])) {
            $product_option_value = $this->model_extension_module_oasiscatalog->getProductOptionValueId($product_id, $data['product_option'][0]['product_option_value'][0]['option_value_id']);
        }

        $args = [
            'product_id_oasis' => $product->id,
            'rating' => $product->rating,
            'option_value_id' => $product_option_value['product_option_value_id'] ?? '',
            'product_id' => $product_id,
        ];
        $this->model_extension_module_oasiscatalog->addOasisProduct($args);

        return $product_id;
    }

    /**
     * @param     $data
     * @param     $product_o
     * @param int $option_value_id
     * @return array
     * @throws Exception
     */
    public function setProduct($data, $product_o, int $option_value_id = 0): array
    {
        $this->load->model('catalog/product');
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();

        if (empty($data['model']) || $data['model'] === $product_o->article) {
            $product['product_description'] = [];

            foreach ($languages as $language) {
                $product['product_description'][$language['language_id']] = [
                    'name' => htmlspecialchars($product_o->full_name, ENT_QUOTES),
                    'description' => htmlspecialchars('<p>' . nl2br($product_o->description) . '</p>', ENT_QUOTES),
                    'meta_title' => htmlspecialchars($product_o->full_name, ENT_QUOTES),
                    'meta_description' => '',
                    'meta_keyword' => '',
                    'tag' => '',
                ];
            }
            unset($language);

            $product['price'] = $product_o->price;
        } else {
            $product['product_description'] = $this->model_catalog_product->getProductDescriptions($data['product_id']);
            $product['price'] = $data['price'];
        }

        $product['model'] = $data['model'] ?? htmlspecialchars($product_o->article, ENT_QUOTES);
        $product['sku'] = $data['sku'] ?? '';
        $product['upc'] = $data['upc'] ?? '';
        $product['ean'] = $data['ean'] ?? '';
        $product['jan'] = $data['jan'] ?? '';
        $product['isbn'] = $data['isbn'] ?? '';
        $product['mpn'] = $data['mpn'] ?? '';
        $product['location'] = $data['location'] ?? '';
        $product['tax_class_id'] = $data['tax_class_id'] ?? '0';
        $product['minimum'] = $data['minimum'] ?? 1;
        $product['subtract'] = $data['subtract'] ?? 1;
        $product['stock_status_id'] = $data['stock_status_id'] ?? '0';
        $product['shipping'] = $data['shipping'] ?? 1;
        $product['date_available'] = $data['date_available'] ?? date('Y-m-d');
        $product['length'] = $data['length'] ?? '';
        $product['width'] = $data['width'] ?? '';
        $product['height'] = $data['height'] ?? '';
        $product['length_class_id'] = $data['length_class_id'] ?? 1;
        $product['weight'] = $data['weight'] ?? '';
        $product['weight_class_id'] = $data['weight_class_id'] ?? 1;
        $product['sort_order'] = $data['sort_order'] ?? 1;
        $product['manufacturer'] = $data['manufacturer'] ?? '';
        $product['manufacturer_id'] = $data['manufacturer_id'] ?? '0';
        $product['category'] = $data['category'] ?? '';

        if (isset($data['product_category'])) {
            $product['product_category'] = $data['product_category'];
        }

        $product['filter'] = $data['filter'] ?? '';
        $product['product_store'] = $data['product_store'] ?? $this->getStores();
        $product['download'] = $data['download'] ?? '';
        $product['related'] = $data['related'] ?? '';

        if (!empty($data['product_related'])) {
            $product['product_related'] = $data['product_related'];
        }

        $product['product_attribute'] = $this->addAttributes($product_o->attributes);

        $product['option'] = $data['option'] ?? '';

        if (!empty($data['product_option'])) {
            $product['product_option'] = $data['product_option'];
            $product['quantity'] = array_sum(array_column($data['product_option'][0]['product_option_value'], 'quantity'));
        } else {
            $product['quantity'] = $product_o->total_stock;
        }

        if ($product_o->rating === 5) {
            if ($option_value_id) {
                foreach ($product['product_option'][0]['product_option_value'] as $key => $value) {
                    if ($value['option_value_id'] == $option_value_id) {
                        $product['product_option'][0]['product_option_value'][$key]['quantity'] = 1000000;
                    }
                }
            }
            $product['quantity'] = 1000000;
        }

        if ($product['quantity'] > 0 || $product_o->rating === 5) {
            $product['status'] = 1;
        } else {
            $product['status'] = 0;
        }

        $product['image'] = $data['image'] ?? '';

        if (isset($data['product_image'])) {
            $product['product_image'] = $data['product_image'];
        }

        $product['points'] = $data['points'] ?? '';
        $product['product_reward'] = $data['product_reward'] ?? [1 => ['points' => '']];
        $product['product_seo_url'] = $data['product_seo_url'] ?? $this->getSeoUrl($this->getStores(), $this->transliter($product_o->full_name));
        $product['product_layout'] = $data['product_layout'] ?? [0 => ''];

        return $product;
    }

    /**
     * @param $categories
     * @return array
     * @throws Exception
     */
    public function getArrCategories($categories): array
    {
        $result = [];
        foreach ($categories as $category) {
            $categories_oc = $this->getCategories($category);

            foreach ($categories_oc as $category_oc) {
                $needed_cat = array_search($category_oc, $result);
                if ($needed_cat === false) {
                    $result[] = $category_oc;
                }
            }
        }

        return $result;
    }

    /**
     * @param       $data
     * @param int   $parent_id
     * @param false $sw
     */
    public function buildTreeCats($data, int $parent_id = 0, bool $sw = false)
    {
        if (empty($data[$parent_id])) {
            return;
        }

        $this->treeCats .= $sw ? '<fieldset><legend></legend>' . PHP_EOL : '';
        for ($i = 0; $i < count($data[$parent_id]); $i++) {
            $checked = $data[$parent_id][$i]['level'] == 1 ? ' checked' : '';
            $this->treeCats .= '<label><input id="categories" type="checkbox" name="category[]" value="' . $data[$parent_id][$i]['id'] . '"' . $checked . '> ' . $data[$parent_id][$i]['name'] . '</label>' . PHP_EOL;
            $this->buildTreeCats($data, $data[$parent_id][$i]['id'], true);
        }
        $this->treeCats .= $sw ? '</fieldset>' . PHP_EOL : '';
    }

    /**
     * @param $id
     * @return array|false
     * @throws Exception
     */
    public function getCategories($id)
    {
        $category = $this->searchObject($this->cat_oasis, $id);

        if (!$category) {
            return false;
        }

        $this->load->model('catalog/category');
        $category_id_oc = $this->getIdByKeyword($category->slug);

        if ($category_id_oc) {
            $data = array_filter(array_column($this->model_catalog_category->getCategoryPath($category_id_oc), 'path_id'));
        } else {
            $data = array_filter(array_column($this->model_catalog_category->getCategoryPath($this->addCategory($id)), 'path_id'));
        }

        return $data;
    }

    /**
     * @param $id
     * @return mixed
     * @throws Exception
     */
    public function addCategory($id)
    {
        $category = $this->searchObject($this->cat_oasis, $id);

        if (!$category) {
            return false;
        }

        $category_id_oc = $this->getIdByKeyword($category->slug);

        if ($category_id_oc) {
            return $category_id_oc;
        }

        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        $data['category_description'] = [];

        foreach ($languages as $language) {
            $data['category_description'][$language['language_id']] = [
                'name' => $category->name,
                'description' => '',
                'meta_title' => $category->name,
                'meta_description' => '',
                'meta_keyword' => '',
            ];
        }
        unset($language);

        $data['path'] = '';
        $data['parent_id'] = 0;

        if (!is_null($category->parent_id)) {
            $parent_category_id = $this->getIdByKeyword($this->searchObject($this->cat_oasis, $category->parent_id)->slug);

            if ($parent_category_id) {
                $data['parent_id'] = $parent_category_id;
            } else {
                $data['parent_id'] = $this->addCategory($category->parent_id);
            }
        }

        $data['filter'] = '';
        $data['category_store'] = $this->getStores();
        $data['image'] = '';
        $data['column'] = 1;
        $data['sort_order'] = 0;
        $data['status'] = true;
        $data['category_seo_url'] = $this->getSeoUrl($data['category_store'], $category->slug);
        $data['category_layout'] = [0 => ''];

        $this->load->model('catalog/category');

        return $this->model_catalog_category->addCategory($data);
    }

    /**
     * @param $attributes
     * @return array
     * @throws Exception
     */
    public function addAttributes($attributes): array
    {
        $this->load->model('catalog/attribute');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();
        $data = [];

        foreach ($attributes as $attribute) {
            $name = $attribute->name;
            if ($name !== $this->var_size) {
                $neededAttribute = [];

                $attributes_store = $this->model_catalog_attribute->getAttributes();
                $neededAttribute = array_filter($attributes_store, function ($e) use ($name) {
                    return $e['name'] == $name;
                });

                if ($neededAttribute) {
                    $attr = array_shift($neededAttribute);

                    $key_attr = array_search($attr['name'], array_column($data, 'name'));

                    if ($key_attr !== false) {
                        foreach ($data[$key_attr]['product_attribute_description'] as $key => $value) {
                            $data[$key_attr]['product_attribute_description'][$key]['text'] .= ', ' . $attribute->value;
                        }
                        unset($key, $value);
                    } else {
                        $data[] = [
                            'name' => $attr['name'],
                            'attribute_id' => $attr['attribute_id'],
                            'product_attribute_description' => $this->toLanguagesArr($languages, 'text', $attribute->value),
                        ];
                    }
                } else {
                    $data_attribute['attribute_description'] = $this->toLanguagesArr($languages, 'name', $attribute->name);
                    $data_attribute['attribute_group_id'] = $this->getAttributeGroupId($languages);
                    $data_attribute['sort_order'] = '';

                    $data[] = [
                        'name' => $attribute->name,
                        'attribute_id' => $this->model_catalog_attribute->addAttribute($data_attribute),
                        'product_attribute_description' => $this->toLanguagesArr($languages, 'text', $attribute->value),
                    ];
                }
                unset($attr, $key_attr, $data_attribute);
            }
        }
        unset($attribute);

        return $data;
    }

    /**
     * @param $id
     * @return bool
     * @throws Exception
     */
    public function addBrand($id)
    {
        $brand = $this->searchObject($this->mf_oasis, $id);

        if (!$brand) {
            return false;
        }

        $brand_oc = $this->getIdByKeyword($brand->slug);

        if ($brand_oc) {
            return $brand_oc;
        }

        $data['name'] = $brand->name;
        $data['manufacturer_store'] = $this->getStores();
        $data['sort_order'] = '';
        $data['manufacturer_seo_url'] = $this->getSeoUrl($data['manufacturer_store'], $brand->slug);

        $data_img = [
            'folder_name' => 'catalog/oasis/manufacturers',
            'img_url' => $brand->logotype,
            'img_name' => $brand->slug,
            'count' => 0,
        ];

        $data['image'] = $this->saveImg($data_img);

        $this->load->model('catalog/manufacturer');

        return $this->model_catalog_manufacturer->addManufacturer($data);
    }

    /**
     * @param $option
     * @return mixed
     * @throws Exception
     */
    public function addOption($option)
    {
        $this->load->model('catalog/option');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        $data['option_description'] = $this->toLanguagesArr($languages, 'name', $option['name']);
        $data['type'] = 'radio';
        $data['sort_order'] = '';
        foreach ($option['value'] as $item) {
            $data['option_value'][] = [
                'option_value_id' => '',
                'option_value_description' => $this->toLanguagesArr($languages, 'name', $item),
                'image' => '',
                'sort_order' => '',
            ];
        }
        unset($item);

        return $this->model_catalog_option->addOption($data);
    }

    /**
     * @param $option_id
     * @param $value
     * @throws Exception
     */
    public function editOption($option_id, $value)
    {
        $this->load->model('catalog/option');
        $this->load->model('localisation/language');

        $data['option_description'] = $this->model_catalog_option->getOptionDescriptions($option_id);
        $data['type'] = 'radio';
        $data['sort_order'] = '';

        $option_values = $this->model_catalog_option->getOptionValueDescriptions($option_id);

        $languages = $this->model_localisation_language->getLanguages();

        $option_values[] = [
            'option_value_id' => '',
            'option_value_description' => $this->toLanguagesArr($languages, 'name', $value),
            'image' => '',
            'sort_order' => '',
        ];

        $data['option_value'] = $option_values;

        $this->model_catalog_option->editOption($option_id, $data);
    }

    /**
     * @param $data
     * @return array
     */
    public function setOption($data): array
    {
        $option[0] = [
            'product_option_id' => '',
            'name' => $data['option']['name'],
            'option_id' => $data['option']['option_id'],
            'type' => $data['option']['type'],
            'required' => 1,
        ];

        $option[0]['product_option_value'] = [];

        foreach ($data['values'] as $value) {
            $option[0]['product_option_value'][] = [
                'option_value_id' => $value['option_value_id'],
                'product_option_value_id' => '',
                'quantity' => $value['quantity'],
                'subtract' => 1,
                'price_prefix' => '+',
                'price' => '',
                'points_prefix' => '+',
                'points' => '',
                'weight_prefix' => '+',
                'weight' => '',
            ];
        }
        unset($value);

        return $option;
    }

    /**
     * @param $option_name
     * @param $value
     * @param $quantity
     * @return array
     * @throws Exception
     */
    public function getOption($option_name, $value, $quantity): array
    {
        $this->load->model('catalog/option');

        $data['option'] = $this->model_catalog_option->getOptions(['filter_name' => $option_name]);

        if (!$data['option']) {
            $opt['name'] = $option_name;
            $opt['value'][] = $value;
            $data['option'] = $this->model_catalog_option->getOption($this->addOption($opt));
        } else {
            $data['option'] = $data['option'][0];
        }
        unset($opt);

        $values = $this->getOptionValue($data['option']['option_id'], $value);

        if ($values === false) {
            $this->editOption($data['option']['option_id'], $value);

            $values = $this->getOptionValue($data['option']['option_id'], $value);
        }

        $values['quantity'] = $quantity;

        $data['values'][] = $values;

        return $data;
    }

    /**
     * @param $option_id
     * @param $needle
     * @return array|bool
     * @throws Exception
     */
    public function getOptionValue($option_id, $needle)
    {
        $this->load->model('catalog/option');

        $option_values = $this->model_catalog_option->getOptionValues($option_id);
        $key = array_search($needle, array_column($option_values, 'name'));

        return $key !== false ? $option_values[$key] : false;
    }

    /**
     * @param $args
     * @return bool|array
     */
    public function getProductOasis($args)
    {
        if (isset($args['ids']) && $args['ids'] !== '') {
            $args['ids'] = $args['ids']['id'];
        }

        return $this->curl_query(self::API_V4, self::API_PRODUCTS, $args);
    }

    /**
     * @param $languages
     * @return mixed
     * @throws Exception
     */
    public function getAttributeGroupId($languages)
    {
        $this->load->model('catalog/attribute_group');
        $attribute_groups = $this->model_catalog_attribute_group->getAttributeGroups();

        $name = 'Характеристики';
        $key = array_search($name, array_column($attribute_groups, 'name'));

        if ($key !== false) {
            $attribute_group_id = $attribute_groups[$key]['attribute_group_id'];
        } else {
            $data_attribute_group = [];

            foreach ($languages as $language) {
                $data_attribute_group['attribute_group_description'][$language['language_id']] = [
                    'name' => $name,
                ];
            }
            unset($language);

            $data_attribute_group['sort_order'] = '';

            $attribute_group_id = $this->model_catalog_attribute_group->addAttributeGroup($data_attribute_group);
        }

        return $attribute_group_id;
    }

    /**
     * @param $stores
     * @param $slug
     * @return array
     * @throws Exception
     */
    public function getSeoUrl($stores, $slug): array
    {
        $data = [];

        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($stores as $store) {
            $i = 0;
            $postfix = '';
            foreach ($languages as $language) {
                if ($i > 0) {
                    $postfix = '-' . $i;
                }
                $data[$store][$language['language_id']] = $slug . $postfix;
                $i++;
            }
            unset($language);
        }
        unset($store);

        return $data;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getStores(): array
    {
        $data = [];

        $this->load->model('setting/store');

        $stores = $this->model_setting_store->getStores();

        if ($stores) {
            foreach ($stores as $store) {
                $data[] = $store['store_id'];
            }
            unset($store);
        } else {
            $data = [0];
        }

        return $data;
    }

    /**
     * @param $seo_url
     * @return integer|bool
     * @throws Exception
     */
    public function getIdByKeyword($seo_url)
    {
        $this->load->model('design/seo_url');

        $seo_urls = $this->model_design_seo_url->getSeoUrlsByKeyword($seo_url);

        if ($seo_urls) {
            $result = explode('=', $seo_urls[0]['query']);

            return $result[1];
        }

        return false;
    }

    /**
     * @param array $args
     * @return bool|mixed
     */
    public function getCategoriesOasis(array $args = [])
    {
        return $this->curl_query(self::API_V4, self::API_CATEGORIES, $args);
    }

    /**
     * @param array $args
     * @return bool|mixed
     */
    public function getCurrenciesOasis(array $args = [])
    {
        return $this->curl_query(self::API_V4, self::API_CURRENCIES, $args);
    }

    /**
     * @param array $args
     * @return bool|mixed
     */
    public function getBrandsOasis(array $args = [])
    {
        return $this->curl_query(self::API_V3, self::API_BRANDS, $args);
    }

    /**
     * @param $data
     * @param $id
     * @return bool|mixed
     */
    public function searchObject($data, $id)
    {
        $neededObject = array_filter($data, function ($e) use ($id) {
            return $e->id == $id;
        });

        if (!$neededObject) {
            return false;
        }

        return array_shift($neededObject);
    }

    /**
     * @param $languages
     * @param $key
     * @param $value
     * @return array
     */
    public function toLanguagesArr($languages, $key, $value): array
    {
        $data = [];

        foreach ($languages as $language) {
            $data[$language['language_id']] = [
                $key => $value,
            ];
        }
        unset($language);

        return $data;
    }

    /**
     * @param       $version
     * @param       $type
     * @param array $args
     * @return bool|mixed
     */
    public function curl_query($version, $type, array $args = [])
    {
        $args_pref = [
            'key' => API_KEY,
            'format' => 'json',
        ];
        $args = array_merge($args_pref, $args);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_URL . $version . $type . '?' . http_build_query($args));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = json_decode(curl_exec($ch));
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $http_code === 200 ? $result : false;
    }

    /**
     * @param $data
     * @return bool|string
     */
    protected function saveImg($data)
    {
        $ext = pathinfo($data['img_url']);

        if (!array_key_exists('extension', $ext) || $ext['extension'] === 'tif') {
            return false;
        }

        $data['count'] === 0 ? $count = '' : $count = '-' . $data['count'];

        if (empty($data['img_name']) || $data['img_name'] === '') {
            $data['img_name'] = $ext['filename'];
        }

        $img = $this->imgFolder($data['folder_name']) . $data['img_name'] . $count . '.' . $ext['extension'];

        if (!file_exists($img)) {
            $pic = file_get_contents($data['img_url'], true, stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => true]]));

            if (!preg_match("/200|301/", $http_response_header[0])) {
                return false;
            }
            file_put_contents($img, $pic);
        }

        return $data['folder_name'] . '/' . $data['img_name'] . $count . '.' . $ext['extension'];
    }

    /**
     * @param $folder
     * @return bool|string
     */
    protected function imgFolder($folder)
    {
        $path = DIR_IMAGE . $folder . '/';
        if (!file_exists($path)) {
            $create = mkdir($path, 0755, true);
            if (!$create) {
                return false;
            }
        }

        return $path;
    }

    /**
     * @param $str
     * @return string
     */
    protected function transliter($str): string
    {
        $arr_trans = [
            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'E',
            'Ж' => 'J',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'Y',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'H',
            'Ц' => 'TS',
            'Ч' => 'CH',
            'Ш' => 'SH',
            'Щ' => 'SCH',
            'Ъ' => '',
            'Ы' => 'YI',
            'Ь' => '',
            'Э' => 'E',
            'Ю' => 'YU',
            'Я' => 'YA',
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'j',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sch',
            'ъ' => 'y',
            'ы' => 'yi',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            '.' => '-',
            ' ' => '-',
            '?' => '-',
            '/' => '-',
            '\\' => '-',
            '*' => '-',
            ':' => '-',
            '>' => '-',
            '|' => '-',
            '\'' => '',
            '(' => '',
            ')' => '',
            '!' => '',
            '@' => '',
            '%' => '',
            '`' => '',
        ];
        $str = str_replace(['-', '+', '.', '?', '/', '\\', '*', ':', '*', '|'], ' ', $str);
        $str = htmlspecialchars_decode($str);
        $str = strip_tags($str);
        $pattern = '/[\w\s\d]+/u';
        preg_match_all($pattern, $str, $result);
        $str = implode('', $result[0]);
        $str = preg_replace('/[\s]+/us', ' ', $str);
        $str_trans = strtr($str, $arr_trans);

        return strtolower($str_trans);
    }

    /**
     * @param $id
     * @param $msg
     */
    protected function saveToLog($id, $msg)
    {
        return;
        $str = date('Y-m-d H:i:s') . ' | product_id=' . $id . ' | ' . $msg . PHP_EOL;
        $filename = DIR_LOGS . 'oasiscatalog_log.txt';
        if (!file_exists($filename)) {
            $fp = fopen($filename, 'wb');
            fwrite($fp, $str);
            fclose($fp);
        } else {
            file_put_contents($filename, $str, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * @throws Exception
     */
    public function install()
    {
        $settings = [
            'oasiscatalog_status' => 0,
            'oasiscatalog_api_key' => '',
        ];

        $this->model_setting_setting->editSetting('oasiscatalog', $settings);

        $this->load->model('extension/module/oasiscatalog');
        $this->model_extension_module_oasiscatalog->install();
    }

    /**
     * @throws Exception
     */
    public function uninstall()
    {
        $this->model_setting_setting->deleteSetting('oasiscatalog');
    }

    /**
     * @return bool
     */
    protected function validate(): bool
    {
        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
