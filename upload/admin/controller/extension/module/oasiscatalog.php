<?php
/**
 * Class ControllerExtensionModuleOasiscatalog
 */

class ControllerExtensionModuleOasiscatalog extends Controller
{
    private $error = [];
    private $treeCats = '';
    private const ROUTE = 'extension/module/oasiscatalog';

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

            if (isset($this->request->post['moscow']) && $this->request->post['moscow'] !== '') {
                $post_data['oasiscatalog_args']['moscow'] = $this->request->post['moscow'];
            }

            if (isset($this->request->post['europe']) && $this->request->post['europe'] !== '') {
                $post_data['oasiscatalog_args']['europe'] = $this->request->post['europe'];
            }

            if (isset($this->request->post['remote']) && $this->request->post['remote'] !== '') {
                $post_data['oasiscatalog_args']['remote'] = $this->request->post['remote'];
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

            if (isset($this->request->post['limit']) && $this->request->post['limit'] !== '') {
                $post_data['oasiscatalog_args']['limit'] = $this->request->post['limit'];
                $args = $this->config->get('oasiscatalog_args');

                if (!empty($args['limit'])) {
                    if ($args['limit'] !== $post_data['oasiscatalog_args']['limit']) {
                        $post_data['oasiscatalog_step'] = 0;
                    } else {
                        $post_data['oasiscatalog_step'] = (int)$this->config->get('oasiscatalog_step');
                    }
                }
            }

            if (isset($this->request->post['factor']) && $this->request->post['factor'] !== '') {
                $post_data['oasiscatalog_factor'] = $this->request->post['factor'];
            }

            if (isset($this->request->post['increase']) && $this->request->post['increase'] !== '') {
                $post_data['oasiscatalog_increase'] = $this->request->post['increase'];
            }

            if (isset($this->request->post['dealer']) && $this->request->post['dealer'] !== '') {
                $post_data['oasiscatalog_dealer'] = $this->request->post['dealer'];
            }

            $post_data['oasiscatalog_progress_total'] = (int)$this->config->get('oasiscatalog_progress_total');
            $post_data['oasiscatalog_progress_item'] = (int)$this->config->get('oasiscatalog_progress_item');
            $post_data['oasiscatalog_progress_date'] = $this->config->get('oasiscatalog_progress_date');

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
        $data['cron_product'] = 'php ' . realpath(str_replace('admin', '', DIR_APPLICATION)) . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'oasis_cli.php --key=' . CRON_KEY;
        $data['cron_stock'] = $data['cron_product'] . ' --up';

        if ($data['api_key']) {
            $currencies = $this->getCurrenciesOasis();
            $data['api_key_status'] = (bool)$currencies;

            if ($data['api_key_status']) {
                $args = $this->config->get('oasiscatalog_args');
                if ($args) {
                    $data += $args;
                }
                $data['tax_class_id'] = $this->config->get('oasiscatalog_tax_class_id');
                $data['factor'] = $this->config->get('oasiscatalog_factor');
                $data['increase'] = $this->config->get('oasiscatalog_increase');
                $data['dealer'] = $this->config->get('oasiscatalog_dealer');

                $progressTotal = (int)$this->config->get('oasiscatalog_progress_total');
                $progressItem = (int)$this->config->get('oasiscatalog_progress_item');
                $progressStepTotal = (int)$this->config->get('oasiscatalog_progress_step_total');
                $progressStepItem = (int)$this->config->get('oasiscatalog_progress_step_item');
                $data['progressDate'] = $this->config->get('oasiscatalog_progress_date');
                $data['limit'] = !empty($args['limit']) ? (int)$args['limit'] : 0;

                if (!empty($data['limit'])) {
                    $step = (int)$this->config->get('oasiscatalog_step');
                    $stepTotal = !empty($progressTotal) ? ceil($progressTotal / $data['limit']) : 0;
                    $data['text_progress_step'] = sprintf($this->language->get('text_progress_step'), ++$step, $stepTotal);
                }

                if (!empty($progressTotal) && !empty($progressItem)) {
                    $data['percentTotal'] = round(($progressItem / $progressTotal) * 100);
                } else {
                    $data['percentTotal'] = 0;
                }

                if (!empty($progressStepTotal) && !empty($progressStepItem)) {
                    $data['percentStep'] = round(($progressStepItem / $progressStepTotal) * 100);
                } else {
                    $data['percentStep'] = 0;
                }

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
                $categories = $this->getCategoriesOasis(['fields' => 'id,parent_id,root,level,slug,name,path']);

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
     * @param array $args
     * @return bool|mixed
     */
    public function getCategoriesOasis(array $args = [])
    {
        return $this->curl_query('categories', $args);
    }

    /**
     * @param array $args
     * @return bool|mixed
     */
    public function getCurrenciesOasis(array $args = [])
    {
        return $this->curl_query('currencies', $args);
    }

    /**
     * @param       $type
     * @param array $args
     * @return bool|mixed
     */
    public function curl_query($type, array $args = [])
    {
        $args_pref = [
            'key' => API_KEY,
            'format' => 'json',
        ];
        $args = array_merge($args_pref, $args);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.oasiscatalog.com/v4/' . $type . '?' . http_build_query($args));
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
        $settings = [
            'oasiscatalog_status' => 0,
            'oasiscatalog_api_key' => '',
        ];

        $this->model_setting_setting->editSetting('oasiscatalog', $settings);
        $this->load->model(self::ROUTE);
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
