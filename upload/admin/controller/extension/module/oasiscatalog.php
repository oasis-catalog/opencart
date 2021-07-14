<?php

class ControllerExtensionModuleOasiscatalog extends Controller
{
    private $error = [];
    private const ROUTE = 'extension/module/oasiscatalog';
    private const API_URL = 'https://api.oasiscatalog.com/v4/';

    public function index()
    {
        $this->load->language(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $module_data = [];

            foreach ($this->request->post as $key => $value) {
                $module_data['oasiscatalog_' . $key] = $value;
            }

            $this->model_setting_setting->editSetting('oasiscatalog', $module_data);

            $this->cache->delete('oasiscatalog');
            $this->session->data['success'] = $this->language->get('heading_title');
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

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view(self::ROUTE, $data));
    }

    public function getTestData()
    {
        $json = [];

        $this->load->language(self::ROUTE);
        $this->load->model('setting/setting');

        $fields = [
            'key' => $this->config->get('oasiscatalog_api_key'),
            'currency' => 'rub',
            'format' => 'json',
            'no_vat' => 0,
            'rating' => 1,
            'category' => 3257,
        ];

        $type = 'products';

        $data_query = $this->curl_query($type, $fields);

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {

            $json['api_key'] = $fields['key'];
            $json['success'] = 'успешно';
            $json['produts'] = $data_query;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function curl_query($type, $fields = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_URL . $type . '?' . http_build_query($fields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = json_decode(curl_exec($ch));
        curl_close($ch);

        return $result;
    }

    public function install()
    {
        $this->load->model('setting/setting');
        $settings = [
            'oasiscatalog_status' => 0,
            'oasiscatalog_api_key' => '',
        ];

        $this->model_setting_setting->editSetting('oasiscatalog', $settings);
    }

    public function uninstall()
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('oasiscatalog');
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
