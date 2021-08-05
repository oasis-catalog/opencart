<?php
/**
 * Class ControllerExtensionModuleOasiscatalog
 */

class ControllerExtensionModuleOasiscatalog extends Controller
{
    private $error = [];
    private $cat_oasis = [];
    private const ROUTE = 'extension/module/oasiscatalog';
    private const API_URL = 'https://api.oasiscatalog.com/';
    private const API_V4 = 'v4/';
    private const API_V3 = 'v3/';
    private const API_CURRENCIES = 'currencies';
    private const API_CATEGORIES = 'categories';
    private const API_PRODUCTS = 'products';
    private const API_BRANDS = 'brands';
    private const API_CAT_FIELDS = 'id,parent_id,root,level,slug,name,path';

    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->load->model('setting/setting');

        define('API_KEY', $this->config->get('oasiscatalog_api_key'));
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

        if ($data['api_key']) {
            $currencies = $this->getCurrenciesOasis();
            $data['api_key_status'] = (bool)$currencies;

            if ($data['api_key_status']) {
                $data['currencies'] = [];

                foreach ($currencies as $currency) {
                    $data['currencies'][$currency->code] = $currency->full_name;
                }
                unset($currency);

                $this->load->model('localisation/tax_class');

                $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

                $args['fields'] = self::API_CAT_FIELDS;

                $categories = $this->getCategoriesOasis($args);
                $dl = '&nbsp;&gt;&nbsp;';
                $result = [];

                foreach ($categories as $item) {
                    $parent = isset($result[$item->parent_id]) ? $result[$item->parent_id] . $dl : '';
                    $result[$item->id] = $parent . $item->name;
                }

                $data['categories'] = $result;

                unset($result, $item, $parent, $dl);

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

            $count = isset($this->request->post['count']) ? (int)$this->request->post['count'] : 0;

            $args = [
                'currency' => $this->request->post['currency'] ?? 'rub',
                'no_vat' => $this->request->post['no_vat'] ?? 1,
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
            }

            if (isset($this->request->post['tax_class']) && $this->request->post['tax_class'] !== '') {
                $data['tax_class_id'] = (int)$this->request->post['tax_class'];
            } else {
                $data['tax_class_id'] = 0;
            }

            try {

                $this->cat_oasis = $this->getCategoriesOasis(['fields' => self::API_CAT_FIELDS]);

                if (isset($this->request->post['ids']) && $this->request->post['ids'] !== '') {
                    $products = $this->request->post['ids'];
                    $total_count = (isset($this->request->post['total_count'])) ? $this->request->post['total_count'] : 1;
                } else {
                    $product_obj = $this->getProductOasis(['fields' => 'id'] + $args);

                    $products = [];
                    foreach ($product_obj as $item) {
                        $products[]['id'] = $item->id;
                    }
                    unset($item, $product_obj);

                    $total_count = count($products);
                }

                if ($products) {
                    $args['ids'] = array_shift($products);
                    $product = $this->getProductOasis($args);
                    $msg = $this->product($product[0], $args, $data);

                    $json['total_count'] = $total_count;
                    $json['ids'] = $products;
                    $json['text'] = $this->language->get('text_products_added');
                    $json['status'] = $msg['status'];
                    $json['countcon'] = $count;
                } else {
                    $json['text'] = $this->language->get('text_error');
                    $json['status'] = $this->language->get('text_no_products');
                }
            } catch (\Exception $exception) {
                $this->saveToLog($count, $exception->getMessage());

                return;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @param       $product
     * @param       $args
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function product($product, $args, array $data = []): array
    {
        $this->load->model('catalog/product');

        $msg = [];

        if (!is_null($product->parent_size_id)) {
            $this->load->language(self::ROUTE);

            $option = $this->getOption($this->language->get('var_size'), $product->size, $product->total_stock);

            $data['option'] = $option['option']['name'];
            $data['product_option'] = $this->setOption($option);

            if ($product->parent_size_id === $product->id) {
                $msg = $this->checkProduct($data, $product);
            } else {
                $args['ids'] = [
                    'id' => $product->parent_size_id,
                ];

                $parent_product = $this->getProductOasis($args);

                if (!empty($parent_product)) {
                    $product_oc = $this->model_catalog_product->getProducts(['filter_model' => $parent_product[0]->article]);

                    if (!$product_oc) {
                        $msg = $this->product($parent_product[0], $args, $data);
                        $product_oc[] = $this->model_catalog_product->getProduct($msg['id']);
                    }

                    $result = $this->editProduct($product_oc[0], $product, $data['product_option']);

                    $msg['status'] = $result ? $this->language->get('text_product_add_size') : $this->language->get('text_product_not_add_size');
                    $msg['id'] = $product_oc[0]['product_id'];
                } else {
                    $msg['status'] = 'Error. Не найдено товаров с таким ID или артикулом';
                    $msg['id'] = $product->parent_size_id;
                }

            }
            unset($product_oc, $result);

        } else {
            $msg = $this->checkProduct($data, $product);
        }

        $this->saveToLog($msg['id'], $msg['status']);

        return $msg;
    }

    /**
     * @param $data
     * @param $product
     * @return array
     * @throws Exception
     */
    public function checkProduct($data, $product): array
    {
        $product_oc = $this->model_catalog_product->getProducts(['filter_model' => $product->article]);

        if (!$product_oc) {
            $msg['status'] = $this->language->get('text_product_add');
            $msg['id'] = $this->addProduct($data, $product);
        } else {
            $result = $this->editProduct($product_oc[0], $product, $data['product_option']);

            $msg['status'] = $result ? $this->language->get('success_product_edit') : $this->language->get('error_product_edit');
            $msg['id'] = $product_oc[0]['product_id'];
        }

        return $msg;
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
        $this->load->language(self::ROUTE);
        $this->load->model('catalog/product');
        $this->load->model('catalog/manufacturer');
        $this->load->model('catalog/category');
        $this->load->model('catalog/attribute');
        $this->load->model('catalog/option');

        $data = $product_info;

        $data['product_option'] = $this->model_catalog_product->getProductOptions($product_info['product_id']);

        if ($product_option) {

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

                $key_option = array_search($product_option[0]['product_option_value'][0]['option_value_id'], array_column($data['product_option'][0]['product_option_value'], 'option_value_id'));

                if (!$key_option) {
                    $data['product_option'][0]['product_option_value'][] = $product_option[0]['product_option_value'][0];
                }
                unset($key_option);
            } else {
                $data['product_option'] = $product_option;
            }
        }

        $data['quantity'] = (int)$product_info['quantity'] + $product_oasis->total_stock;
        $data['product_description'] = $this->model_catalog_product->getProductDescriptions($product_info['product_id']);

        $manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($product_info['manufacturer_id']);

        if ($manufacturer_info) {
            $data['manufacturer'] = $manufacturer_info['name'];
        }

        $categories = $product_oasis->categories;

        $data['product_category'] = [];
        foreach ($categories as $category) {
            $categories_oc = $this->getCategories($category);

            foreach ($categories_oc as $category_oc) {
                $data['product_category'][] = $category_oc;
            }
        }
        unset($categories, $category, $category_oc);

        $images = $this->model_catalog_product->getProductImages($product_info['product_id']);

        $data['product_image'] = [];

        foreach ($images as $key => $value) {
            $data['product_image'][$key] = [
                'image' => $value['image'],
                'sort_order' => $value['sort_order'],
            ];
        }
        unset($key, $value);

        $arr_product = $this->setProduct($data, $product_oasis);

        $this->model_catalog_product->editProduct($product_info['product_id'], $arr_product);

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

        $categories = $product->categories;

        $data['product_category'] = [];
        foreach ($categories as $category) {
            $categories_oc = $this->getCategories($category);
            foreach ($categories_oc as $category_oc) {
                $data['product_category'][] = $category_oc;
            }
        }
        unset($category, $category_oc);

        if (!is_null($product->brand_id)) {
            $data['manufacturer_id'] = $this->addBrand($this->getBrandsOasis(), $product->brand_id);
        }

        foreach ($product->images as $image) {
            if (isset($image->superbig)) {
                $data_img = [
                    'folder_name' => 'catalog/oasis/products',
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

        if (isset($data['product_image'])) {
            $data['image'] = $data['product_image'][0]['image'];
        }

        $arr_product = $this->setProduct($data, $product);

        return $this->model_catalog_product->addProduct($arr_product);
    }

    /**
     * @param $data
     * @param $product_o
     * @return array
     * @throws Exception
     */
    public function setProduct($data, $product_o): array
    {
        $this->load->model('catalog/product');
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();

        $product['product_description'] = [];

        if (isset($data['product_description'])) {
            $product['product_description'] = $data['product_description'];
        } else {
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
        }

        $product['model'] = $data['model'] ?? htmlspecialchars($product_o->article, ENT_QUOTES);
        $product['sku'] = $data['sku'] ?? '';
        $product['upc'] = $data['upc'] ?? '';
        $product['ean'] = $data['ean'] ?? '';
        $product['jan'] = $data['jan'] ?? '';
        $product['isbn'] = $data['isbn'] ?? '';
        $product['mpn'] = $data['mpn'] ?? '';
        $product['location'] = $data['location'] ?? '';
        $product['price'] = $data['price'] ?? $product_o->price;
        $product['tax_class_id'] = $data['tax_class_id'] ?? '0';
        $product['quantity'] = $data['quantity'] ?? $product_o->total_stock;
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
        $product['status'] = $data['status'] ?? 1;
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
        $product['product_attribute'] = $this->addAttributes($product_o->attributes);

        $product['option'] = $data['option'] ?? '';

        if (isset($data['product_option'])) {
            $product['product_option'] = $data['product_option'];
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
        $this->load->language(self::ROUTE);
        $this->load->model('catalog/attribute');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();
        $var_size = $this->language->get('var_size');
        $data = [];

        foreach ($attributes as $attribute) {
            $name = $attribute->name;
            if ($name !== $var_size) {
                $neededAttribute = [];

                $attributes_store = $this->model_catalog_attribute->getAttributes();
                $neededAttribute = array_filter($attributes_store, function ($e) use ($name) {
                    return $e['name'] == $name;
                });

                if ($neededAttribute) {
                    $attr = array_shift($neededAttribute);

                    $key_attr = array_search($attr['name'], array_column($data, 'name'));

                    if ($key_attr) {
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
     * @param $brands
     * @param $id
     * @return bool
     * @throws Exception
     */
    public function addBrand($brands, $id)
    {
        $brand = $this->searchObject($brands, $id);

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

        if ($key) {
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

        if ($ext['extension'] === 'tif') {
            return false;
        }

        $data['count'] === 0 ? $count = '' : $count = '-' . $data['count'];

        if (empty($data['img_name']) || $data['img_name'] === '') {
            $data['img_name'] = $ext['filename'];
        }

        $img = $this->imgFolder($data['folder_name']) . $data['img_name'] . $count . '.' . $ext['extension'];

        if (!file_exists($img)) {
            $pic = file_get_contents($data['img_url']);
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
