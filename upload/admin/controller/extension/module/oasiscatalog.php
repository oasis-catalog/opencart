<?php
/**
 * Class ControllerExtensionModuleOasiscatalog
 */

class ControllerExtensionModuleOasiscatalog extends Controller
{
    private $error = [];
    private const ROUTE = 'extension/module/oasiscatalog';
    private const API_URL = 'https://api.oasiscatalog.com/';
    private const API_V4 = 'v4/';
    private const API_V3 = 'v3/';
    private const API_CURRENCYES = 'currencies';
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

            $post_data['oasiscatalog_status'] = isset($this->request->post['oasiscatalog_status']) ? $this->request->post['oasiscatalog_status'] : 0;
            $post_data['oasiscatalog_api_key'] = isset($this->request->post['oasiscatalog_api_key']) ? $this->request->post['oasiscatalog_api_key'] : '';

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

        if ($data['api_key']) {
            $currencies = $this->getCurrenciesOasis();
            $data['api_key_status'] = $currencies ? true : false;

            if ($data['api_key_status']) {
                $data['currencies'] = [];

                foreach ($currencies as $currency) {
                    $data['currencies'][$currency->code] = $currency->full_name;
                }

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

            $count = isset($this->request->post['count']) ? (int)$this->request->post['count'] : false;

            $args = [
                'currency' => isset($this->request->post['currency']) ? $this->request->post['currency'] : 'rub',
                'no_vat' => isset($this->request->post['no_vat']) ? $this->request->post['no_vat'] : 0,
                'fieldset' => 'full',
                'limit' => 1,
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

            try {
                $oasis_cat = $this->getCategoriesOasis(['fields' => self::API_CAT_FIELDS]);

                //$args['category'] = 3071;
                //$args['ids'] = '00000003555,00000008288';

                $products = $this->curl_query(self::API_V4, self::API_PRODUCTS, $args);
                //d($this->curl_query(self::API_V4, self::API_PRODUCTS . '/00000003555'));

                if ($products) {
                    $data = [];
                    foreach ($products as $product) {
                        $categories = $product->categories;
                        foreach ($categories as $category) {
                            $data['product_category'][] = $this->addCategory($oasis_cat, $category);
                        }

                        if (!is_null($product->brand_id)) {
                            $data['manufacturer_id'] = $this->addBrand($this->getBrandsOasis(), $product->brand_id);
                        } else {
                            $data['manufacturer_id'] = 0;
                        }

                    }
                    unset($product);

                    //d($data);
                    // next

                    $stat_insert = 'Товар добавлен.';
                    $this->saveToLog(date('Ymdhis'), $stat_insert);
                    $json['text'] = 'Ок!';
                    $json['status'] = $stat_insert;
                    $json['countcon'] = $count;
                } else {
                    $json['text'] = 'Error';
                    $json['status'] = 'Нет страниц для обработки';
                }
            } catch (\Exception $exception) {
                $this->saveToLog($count, $exception->getMessage());

                return;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function getTestData()
    {
        $json = [];

        $this->load->language(self::ROUTE);

        $count = isset($this->request->post['count']) ? (int)$this->request->post['count'] : false;

        $args = [
            'currency' => 'rub',
            'no_vat' => 0,
        ];

        $oasis_cat = $this->getCategoriesOasis(['fields' => self::API_CAT_FIELDS]);

        $args['category'] = 3071;
        $args['ids'] = '00000003555,00000008288';
        //$args['ids'] = '00000003555';
        $args['fieldset'] = 'full';

        try {
            $products = $this->curl_query(self::API_V4, self::API_PRODUCTS, $args);

            if ($products) {
                foreach ($products as $product) {
                    $manufacturer_id = $this->addBrand($this->getBrandsOasis(), $product->brand_id);
                    d($manufacturer_id);
                }

                $stat_insert = 'Товар добавлен.';
                $this->saveToLog(date('Ymdhis'), $stat_insert);
                $json['text'] = 'Ок!';
                $json['status'] = $stat_insert;
                $json['countcon'] = $count;
            } else {
                $json['text'] = 'Error';
                $json['status'] = 'Нет страниц для обработки';
            }
        } catch (\Exception $exception) {
            $this->saveToLog($count, $exception->getMessage());

            return;
        }

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            // post data
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @param $categories
     * @param $id
     * @return bool
     * @throws Exception
     */
    public function addCategory($categories, $id)
    {
        $category = $this->searchObject($categories, $id);

        if (!$category) {
            return false;
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

        $category_oc = $this->getIdByKeyword($category->slug);

        if ($category_oc) {
            return $category_oc;
        }

        $data['parent_id'] = 0;

        if (!is_null($category->parent_id)) {
            $parent_category_id = $this->getIdByKeyword($this->searchObject($oasis_cat, $category->parent_id)->slug);

            if ($parent_category_id) {
                $data['parent_id'] = $parent_category_id;
            } else {
                $data['parent_id'] = $this->addCategory($oasis_cat, $category->parent_id);
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

        $category_id = $this->model_catalog_category->addCategory($data);

        return $category_id;
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
        $data['image'] = $brand->logotype;
        $data['sort_order'] = '';
        $data['manufacturer_seo_url'] = $this->getSeoUrl($data['manufacturer_store'], $brand->slug);

        $this->load->model('catalog/manufacturer');

        $manufacturer_id = $this->model_catalog_manufacturer->addManufacturer($data);

        return $manufacturer_id;
    }

    /**
     * @param $stores
     * @param $slug
     * @return array
     * @throws Exception
     */
    public function getSeoUrl($stores, $slug)
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
        }

        return $data;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getStores()
    {
        $data = [];

        $this->load->model('setting/store');

        $stores = $this->model_setting_store->getStores();

        if ($stores) {
            foreach ($stores as $store) {
                $data[] = $store['store_id'];
            }
        } else {
            $data = [0];
        }

        return $data;
    }

    /**
     * @param $seo_url
     * @return bool
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

        $result = array_shift($neededObject);

        return $result;
    }

    /**
     * @param array $args
     * @return bool|mixed
     */
    public function getCategoriesOasis($args = [])
    {
        return $this->curl_query(self::API_V4, self::API_CATEGORIES, $args);
    }

    /**
     * @param array $args
     * @return bool|mixed
     */
    public function getCurrenciesOasis($args = [])
    {
        return $this->curl_query(self::API_V4, self::API_CURRENCYES, $args);
    }

    /**
     * @param array $args
     * @return bool|mixed
     */
    public function getBrandsOasis($args = [])
    {
        return $this->curl_query(self::API_V3, self::API_BRANDS, $args);
    }

    /**
     * @param       $version
     * @param       $type
     * @param array $args
     * @return bool|mixed
     */
    public function curl_query($version, $type, $args = [])
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
     * @param $id
     * @param $msg
     */
    protected function saveToLog($id, $msg)
    {
        $str = date('Y-m-d H:i:s') . ' | page_id=' . $id . ' | ' . $msg . PHP_EOL;
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
    protected function validate()
    {
        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
