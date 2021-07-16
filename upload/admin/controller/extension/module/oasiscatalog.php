<?php
/**
 * Class ControllerExtensionModuleOasiscatalog
 */

class ControllerExtensionModuleOasiscatalog extends Controller
{
    private $error = [];
    private const ROUTE = 'extension/module/oasiscatalog';
    private const API_URL = 'https://api.oasiscatalog.com/v4/';
    private const API_CURRENCYES = 'currencies';
    private const API_CATEGORIES = 'categories';

    /**
     * @throws Exception
     */
    public function index()
    {
        $this->load->language(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

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
        $data['api_key'] = $this->config->get('oasiscatalog_api_key');
        $data['api_key_status'] = false;

        if ($data['api_key']) {
            $args = [
                'key' => $data['api_key'],
            ];

            $currencies = $this->getCurrencies($args);
            $data['api_key_status'] = $currencies ? true : false;

            if ($data['api_key_status']) {
                $data['currencies'] = [];

                foreach ($currencies as $currency) {
                    $data['currencies'][$currency->code] = $currency->full_name;
                }

                $args['format'] = 'json';
                $args['fields'] = 'id,parent_id,root,level,slug,name,path';

                $categories = $this->getCategories($args);
                $dl = '&nbsp;&gt;&nbsp;';
                $result = [];

                foreach ($categories as $item) {
                    $parent = isset($result[$item->parent_id]) ? $result[$item->parent_id] . $dl : '';
                    $result[$item->id] = $parent . $item->name;
                }

                $data['categories'] = $result;

                unset($result, $item, $parent, $dl);

                //next

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

    public function getTestData()
    {
        $test_data = [
            0 => (object) [
                'id' => 1906,
                'parent_id' => 0,
                'root' => 1906,
                'level' => 1,
                'slug' => 'vip',
                'name' => 'VIP',
                'path' => 'vip',
            ],
            1 => (object) [
                'id' => 2016,
                'parent_id' => 1906,
                'root' => 1906,
                'level' => 2,
                'slug' => 'ofisnie-aksessuari',
                'name' => 'Офисные аксессуары',
                'path' => 'vip/ofisnie-aksessuari',
            ],
            2 => (object) [
                'id' => 2026,
                'parent_id' => 2016,
                'root' => 1906,
                'level' => 3,
                'slug' => 'podarochnie-nabori',
                'name' => 'Подарочные наборы',
                'path' => 'vip/ofisnie-aksessuari/podarochnie-nabori',
            ],
            3 => (object) [
                'id' => 2222,
                'parent_id' => 2026,
                'root' => 1906,
                'level' => 4,
                'slug' => 'podarochnie-nabori321354',
                'name' => 'Подарочные наборы321654',
                'path' => 'vip/ofisnie-aksessuari/podarochnie-nabori321654',
            ],
            4 => (object) [
                'id' => 2020,
                'parent_id' => 2016,
                'root' => 1906,
                'level' => 3,
                'slug' => 'bloknoti',
                'name' => 'Блокноты',
                'path' => 'vip/ofisnie-aksessuari/bloknoti',
            ],
            5 => (object) [
                'id' => 2030,
                'parent_id' => 1906,
                'root' => 1906,
                'level' => 2,
                'slug' => 'ofisnie-aksessuari222',
                'name' => 'Офисные аксессуары1222',
                'path' => 'vip/ofisnie-aksessuari222',
            ],
        ];
        //d($this->sortArr($test_data));

        $json = [];

        $this->load->language(self::ROUTE);
        $this->load->model('setting/setting');

        $args = [
            'key' => $this->config->get('oasiscatalog_api_key'),
            'currency' => 'rub',
            'format' => 'json',
            'no_vat' => 0,
            'rating' => 1,
            'category' => 3257,
        ];

        $type = 'products';

        //$data_query = $this->curl_query($type, $args);

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $json['success'] = $this->request->post['currency'];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function sortArr ($data = []) {
        $result = [];

        foreach ($data as $item) {
            $parent = isset($result[$item->parent_id]) ? $result[$item->parent_id] . ' > ' : '';
            $result[$item->id] = $parent . $item->name;
        }

        return$result;
    }

    /**
     * @param array $args
     * @return bool|mixed
     */
    public function getCategories($args = [])
    {
        return $this->curl_query(self::API_CATEGORIES, $args);
    }

    /**
     * @param array $args
     * @return bool|mixed
     */
    public function getCurrencies($args = [])
    {
        return $this->curl_query(self::API_CURRENCYES, $args);
    }

    /**
     * @param       $type
     * @param array $args
     * @return bool|mixed
     */
    public function curl_query($type, $args = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_URL . $type . '?' . http_build_query($args));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = json_decode(curl_exec($ch));
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $http_code === 200 ? $result : false;
    }

    /**
     * @throws Exception
     */
    public function install()
    {
        $this->load->model('setting/setting');
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
        $this->load->model('setting/setting');
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
