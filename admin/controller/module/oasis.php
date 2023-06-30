<?php

namespace Opencart\Admin\Controller\Extension\Oasiscatalog\Module;

require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/Cli.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/Api.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/Main.php');

use Exception;
use Opencart\Admin\Controller\Extension\Oasis\Api;
use Opencart\Admin\Controller\Extension\Oasis\Main;
use Opencart\System\Engine\Controller;

class Oasis extends Controller
{
    private array $error = [];
    private const ROUTE = 'extension/oasiscatalog/module/oasis';
    private const VERSION_MODULE = '4.0.3';

    /**
     * @throws \Exception
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        $params = [
            'short' => 'k:u',
            'long'  => ['key:', 'up'],
        ];

        $options = getopt($params['short'], $params['long']);

        if (isset($options['key']) || isset($options['k'])) {
            new \Opencart\Admin\Controller\Extension\Oasis\Cli($registry);
            exit();
        } else {
            $this->load->model('setting/setting');

            define('API_KEY', $this->config->get('oasiscatalog_api_key'));
            define('CRON_KEY', md5($this->config->get('oasiscatalog_api_key')));
        }
    }

    /**
     * @throws Exception
     */
    public function index(): void
    {
        $this->load->language(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('/extension/oasiscatalog/admin/view/javascript/jquery.tree.js');
        $this->document->addScript('/extension/oasiscatalog/admin/view/javascript/common.js', 'footer');
        $data['footer_scripts'] = $this->document->getScripts('footer');
        $this->document->addStyle('/extension/oasiscatalog/admin/view/stylesheet/stylesheet.css');

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

        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['save'] = $this->url->link(self::ROUTE . '|save', 'user_token=' . $this->session->data['user_token']);
        $data['user_token'] = $this->session->data['user_token'];
        $data['status'] = $this->config->get('oasiscatalog_status');
        $data['api_key'] = API_KEY;
        $data['api_key_status'] = false;
        $data['user_id'] = $this->config->get('oasiscatalog_user_id');
        $data['cron_product'] = 'php ' . realpath(dirname(__FILE__) . '/../../..') . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'cli.php --key=' . CRON_KEY;
        $data['cron_stock'] = $data['cron_product'] . ' --up';
        $data['version'] = self::VERSION_MODULE;

        if ($data['api_key']) {
            $currencies = Api::getCurrenciesOasis();
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

                $lockProcess = Main::checkLockProcess();
                $progress = $this->config->get($lockProcess ? 'progress_tmp' : 'progress');
                $data['progress_class'] = $lockProcess ? 'progress-bar progress-bar-striped progress-bar-animated' : 'progress-bar';

                if ($lockProcess) {
                    $dIcon = '<i class="fa fa-cog fa-spin fa-fw" style="color: #0c7a0a;"></i>';
                } else {
                    $dIcon = '<i class="fa fa-pause" aria-hidden="true" style="color: #e97906;"></i>';
                }

                $data['progress_total'] = $this->language->get('text_progress_total') . ' <span class="oasis-process-icon">' . $dIcon . '</span>';
                $data['progressDate'] = $this->config->get('oasiscatalog_progress_date');
                $data['limit'] = !empty($args['limit']) ? (int)$args['limit'] : 0;

                if (!empty($data['limit'])) {
                    $step = (int)$this->config->get('oasiscatalog_step');
                    $stepTotal = !empty($progress['total']) ? ceil($progress['total'] / $data['limit']) : 0;
                    $data['progress_step'] = sprintf($this->language->get($lockProcess ? 'text_progress_step' : 'text_progress_step_next'), ++$step, $stepTotal);
                }

                if (!empty($progress['total']) && !empty($progress['item'])) {
                    $data['percentTotal'] = round(($progress['item'] / $progress['total']) * 100, 2, PHP_ROUND_HALF_DOWN);
                } else {
                    $data['percentTotal'] = 0;
                }

                if (!empty($progress['step_total']) && !empty($progress['step_item'])) {
                    $data['percentStep'] = round(($progress['step_item'] / $progress['step_total']) * 100, 2, PHP_ROUND_HALF_DOWN);
                } else {
                    $data['percentStep'] = 0;
                }

                $cats = $this->config->get('oasiscatalog_category');

                if ($cats) {
                    $data['category'] = '[' . $cats . ']';
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
                $data['categories'] = self::buildTreeCategories($this->getArrayOasisCategories(), $cats ? explode(',', $cats) : []);
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

    public function save(): void
    {
        $this->load->language(self::ROUTE);
        $json = [];

        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (mb_strlen($this->request->post['oasiscatalog_api_key']) < 10) {
            $json['error']['api_key'] = $this->language->get('error_api_key');
        }

        $post_data['oasiscatalog_status'] = $this->request->post['oasiscatalog_status'] ?? 0;
        $post_data['oasiscatalog_api_key'] = $this->request->post['oasiscatalog_api_key'] ?? '';
        $post_data['oasiscatalog_user_id'] = $this->request->post['oasiscatalog_user_id'] ?? '';
        $post_data['oasiscatalog_args'] = [
            'currency' => $this->request->post['currency'] ?? 'rub',
            'no_vat'   => $this->request->post['no_vat'] ?? '0',
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

        if (!$json) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('oasiscatalog', $post_data);
            $this->cache->delete('oasiscatalog');
            $json['success'] = $this->language->get('text_success');
            $json['redirect'] = $this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token'], true);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Build tree categories
     *
     * @param $data
     * @param array $checkeds
     * @return string
     */
    public static function buildTreeCategories($data, array $checkeds = []): string
    {
        return '<ul id="tree">' . PHP_EOL . self::treeCategories($data, $checkeds) . PHP_EOL . '</ul>' . PHP_EOL;
    }

    /**
     * Prepare tree categories
     *
     * @param $data
     * @param array $checkeds
     * @param string $treeCats
     * @param int $parent_id
     * @param bool $sw
     * @return string
     */
    private static function treeCategories($data, array $checkeds = [], string $treeCats = '', int $parent_id = 0, bool $sw = false): string
    {
        if (!empty($data[$parent_id])) {
            $treeCats .= $sw ? '<ul>' . PHP_EOL : '';

            for ($i = 0; $i < count($data[$parent_id]); $i++) {
                if (empty($checkeds)) {
                    $checked = $data[$parent_id][$i]['level'] == 1 ? ' checked' : '';
                } else {
                    $checked = array_search($data[$parent_id][$i]['id'], $checkeds) !== false ? ' checked' : '';
                }

                $treeCats .= '<li><label><input type="checkbox" name="category[]" id="categories" value="' . $data[$parent_id][$i]['id'] . '"' . $checked . '/> ' . $data[$parent_id][$i]['name'] . '</label>' . PHP_EOL;
                $treeCats = self::treeCategories($data, $checkeds, $treeCats, $data[$parent_id][$i]['id'], true) . '</li>' . PHP_EOL;
            }
            $treeCats .= $sw ? '</ul>' . PHP_EOL : '';
        }

        return $treeCats;
    }

    /**
     * Get array oasis categories
     *
     * @return array
     */
    public function getArrayOasisCategories(): array
    {
        $result = [];
        $categories = Api::getCategoriesOasis(['fields' => 'id,parent_id,root,level,slug,name,path']);

        foreach ($categories as $category) {
            if (empty($result[(int)$category->parent_id])) {
                $result[(int)$category->parent_id] = [];
            }
            $result[(int)$category->parent_id][] = (array)$category;
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function install(): void
    {
        $settings = [
            'oasiscatalog_status'  => 0,
            'oasiscatalog_api_key' => '',
        ];

        $this->model_setting_setting->editSetting('oasiscatalog', $settings);
        $this->load->model(self::ROUTE);
        $this->model_extension_oasiscatalog_module_oasis->install();
    }

    /**
     * @throws Exception
     */
    public function uninstall(): void
    {
        $this->model_setting_setting->deleteSetting('oasiscatalog');
    }

    /**
     * @throws Exception
     */
    public function get_data_progress_bar(): void
    {
        $this->load->language(self::ROUTE);

        $lockProcess = Main::checkLockProcess();
        $pBar = $this->config->get($lockProcess ? 'progress_tmp' : 'progress');
        $args = $this->config->get('oasiscatalog_args');
        $limit = isset($args['limit']) ? intval($args['limit']) : null;
        $stepTotal = !empty($pBar['total']) ? ceil(intval($pBar['total']) / intval($limit)) : 0;
        $oasis_step = intval($this->config->get('oasiscatalog_step'));
        $step = $oasis_step < $stepTotal ? ++$oasis_step : $oasis_step;

        $result = [
            'status_progress' => false,
            'progress_icon'   => '<i class="fa fa-pause" aria-hidden="true" style="color: #e97906;"></i>',
        ];

        if ($limit) {
            $result['progress_step_text'] = sprintf($this->language->get('text_progress_step_next'), strval($step), strval($stepTotal));
            if (!$lockProcess) {
                $result['step_item'] = 0;
            }
        }

        if ($lockProcess && $pBar) {
            $step_item = round(($pBar['step_item'] / $pBar['step_total']) * 100, 2, PHP_ROUND_HALF_DOWN);
            $item = round(($pBar['item'] / $pBar['total']) * 100, 2, PHP_ROUND_HALF_DOWN);

            $result['total_item'] = min($item, 100);
            $result['status_progress'] = true;
            $result['progress_icon'] = '<i class="fa fa-cog fa-spin fa-fw" style="color: #0c7a0a;"></i>';

            if ($limit) {
                $result['step_item'] = $step_item > 99.5 ? 100 : $step_item;
                $result['progress_step_text'] = sprintf($this->language->get('text_progress_step'), strval($step), strval($stepTotal));;
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result));
    }
}
