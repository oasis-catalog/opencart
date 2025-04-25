<?php

namespace Opencart\Admin\Controller\Extension\Oasiscatalog\Module;

require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/Cli.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/Api.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/Main.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/Config.php');

use Exception;
use Opencart\Admin\Controller\Extension\Oasis\Api;
use Opencart\Admin\Controller\Extension\Oasis\Main;
use Opencart\Admin\Controller\Extension\Oasis\Config as OasisConfig;
use Opencart\System\Engine\Controller;

class Oasis extends Controller
{
	private array $error = [];
	private const ROUTE = 'extension/oasiscatalog/module/oasis';
	private const VERSION_MODULE = '4.0.8';

	public function __construct($registry)
	{
		parent::__construct($registry);
	}

	public function index(): void
	{
		$this->load->language(self::ROUTE);
		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addScript('/extension/oasiscatalog/admin/view/javascript/tree.js');
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

		$cf = OasisConfig::instance($this->registry, [
			'init' => true,
			'init_rel' => true
		]);

		$data['status'] = $cf->status;
		$data['api_key'] = $cf->api_key;
		$data['api_key_status'] = false;
		$data['user_id'] = $cf->api_user_id;
		$data['cron_product'] = 'php ' . realpath(dirname(__FILE__) . '/../../..') . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'cli.php --key=' . $cf->getCronKey();
		$data['cron_stock'] = $data['cron_product'] . ' --up';
		$data['version'] = self::VERSION_MODULE;

		if ($data['api_key']) {
			$data['api_key_status'] = $cf->loadCurrencies();

			if ($data['api_key_status']) {
				$data['tax_class_id'] =			$cf->tax_class_id;
				$data['is_price_dealer'] =		$cf->is_price_dealer;
				$data['is_up_photo'] =			$cf->is_up_photo;
				$data['is_import_anytime'] =	$cf->is_import_anytime;
				$data['is_not_up_cat'] =		$cf->is_not_up_cat;
				$data['is_delete_exclude'] =	$cf->is_delete_exclude;
				$data['is_no_vat'] =			$cf->is_no_vat;
				$data['is_not_on_order'] =		$cf->is_not_on_order;
				$data['price_from'] =			$cf->price_from;
				$data['price_to'] =				$cf->price_to;
				$data['price_factor'] =			$cf->price_factor;
				$data['price_increase'] =		$cf->price_increase;
				$data['rating'] =				$cf->rating;
				$data['is_wh_moscow'] =			$cf->is_wh_moscow;
				$data['is_wh_europe'] =			$cf->is_wh_europe;
				$data['is_wh_remote'] =			$cf->is_wh_remote;
				$data['is_cdn_photo'] =			$cf->is_cdn_photo;
				$data['is_cdn_available'] =		$cf->is_cdn_available;

				$optBar = $cf->getOptBar();

				$data['progress_class'] = $optBar['is_process'] ? 'progress-bar progress-bar-striped progress-bar-animated' : 'progress-bar';
				$dIcon = $optBar['is_process'] ? '<i class="fa fa-cog fa-spin fa-fw" style="color: #0c7a0a;"></i>' : 
													'<i class="fa fa-pause" aria-hidden="true" style="color: #e97906;"></i>';

				$data['progress_total'] = $this->language->get('text_progress_total') . ' <span class="oasis-process-icon">' . $dIcon . '</span>';
				$data['progressDate'] = $optBar['date'];

				$data['limit'] = $cf->limit;
				if ($data['limit'] > 0) {
					$data['progress_step'] = sprintf($this->language->get($optBar['is_process'] ? 'text_progress_step' : 'text_progress_step_next'), ($optBar['step'] + 1), $optBar['steps']);
				}
				$data['percentTotal'] = $optBar['p_total'];
				$data['percentStep'] = $optBar['p_step'];

				$data['currencies'] = [];

				foreach ($cf->currencies as $currency) {
					$data['currencies'][$currency['code']] = $currency['name'];
				}

				$this->load->model('localisation/tax_class');
				$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

				$data['categories'] = Main::buildTreeCats($this->getArrayOasisCategories(), $cf->categories, $cf->categories_rel);
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
		$post = $this->request->post;

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (mb_strlen($post['api_key']) < 10) {
			$json['error']['api_key'] = $this->language->get('error_api_key');
		}

		$attr = [
			'module_oasis_status' =>			!empty($post['status']),
			'module_oasis_api_key' =>			$post['api_key'] ?? '',
			'module_oasis_user_id' =>			$post['user_id'] ?? '',
			'module_oasis_opt' => [
				'currency' =>					$post['currency'] ?? 'rub',
				'is_no_vat' =>					!empty($post['is_no_vat']),
				'is_not_on_order' =>			!empty($post['is_not_on_order']),
				'is_price_dealer' =>			!empty($post['is_price_dealer']),

				'price_from' =>					$post['price_from'] ?? '',
				'price_to' =>					$post['price_to'] ?? '',
				'price_to' =>					$post['price_to'] ?? '',
				'price_factor' =>				$post['price_factor'] ?? '',
				'price_increase' =>				$post['price_increase'] ?? '',
				'rating' =>						$post['rating'] ?? '',
				'is_wh_moscow' =>				!empty($post['is_wh_moscow']),
				'is_wh_europe' =>				!empty($post['is_wh_europe']),
				'is_wh_remote' =>				!empty($post['is_wh_remote']),
				'categories' =>					$post['categories'] ?? [],
				'categories_rel' =>				$post['categories_rel'] ?? [],
				'tax_class_id' =>				$post['tax_class_id'] ?? '',
				'limit' =>						$post['limit'] ?? '',
				'is_up_photo' =>				!empty($post['is_up_photo']),
				'is_delete_exclude' =>			!empty($post['is_delete_exclude']),
				'is_import_anytime' =>			!empty($post['is_import_anytime']),
				'is_not_up_cat' =>				!empty($post['is_not_up_cat']),
				'is_cdn_photo' =>				!empty($post['is_cdn_photo']),
			],
			// clear progress
			'module_oasis_progress' => [
				'total' => 0,
				'step' => 0,
				'item' => 0,
				'step_item' => 0,
				'step_total' => 0,
			]
		];

		if (!$json) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('module_oasis', $attr);
			$this->cache->delete('oasiscatalog');

			$this->load->model('setting/event');

			$is_cdn_photo = (!empty($post['is_cdn_photo']) && !empty($post['status'])) ?? 0;

			$this->model_setting_event->editStatusByCode('oasiscatalog_a_v_catalog_product_form', $is_cdn_photo);
			$this->model_setting_event->editStatusByCode('oasiscatalog_a_v_catalog_product_list', $is_cdn_photo);
			$this->model_setting_event->editStatusByCode('oasiscatalog_c_c_product_thumb', $is_cdn_photo);
			$this->model_setting_event->editStatusByCode('oasiscatalog_c_v_account_wishlist_list', $is_cdn_photo);
			$this->model_setting_event->editStatusByCode('oasiscatalog_c_v_checkout_cart_list', $is_cdn_photo);
			$this->model_setting_event->editStatusByCode('oasiscatalog_c_v_common_cart', $is_cdn_photo);
			$this->model_setting_event->editStatusByCode('oasiscatalog_c_v_product_compare', $is_cdn_photo);
			$this->model_setting_event->editStatusByCode('oasiscatalog_c_v_product_product', $is_cdn_photo);



			$json['success'] = $this->language->get('text_success');
			$json['redirect'] = $this->url->link(self::ROUTE, 'user_token=' . $this->session->data['user_token'], true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
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
					$checked = ' checked';
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
		$cf = OasisConfig::instance($this->registry);
		$cf->activate();

		$this->load->model(self::ROUTE);
		$this->model_extension_oasiscatalog_module_oasis->install();

		$this->load->model('setting/event');

		$this->model_setting_event->addEvent([
			'code'        => 'oasiscatalog_a_v_catalog_product_form',
			'description' => 'oasiscatalog, cdn hook image server',
			'trigger'     => 'admin/view/catalog/product_form/before',
			'action'      => 'extension/oasiscatalog/event/event.catalog_product_form',
			'status'      => 0,
			'sort_order'  => 1
		]);
		$this->model_setting_event->addEvent([
			'code'        => 'oasiscatalog_a_v_catalog_product_list',
			'description' => 'oasiscatalog, cdn hook image server',
			'trigger'     => 'admin/view/catalog/product_list/before',
			'action'      => 'extension/oasiscatalog/event/event.catalog_product_list',
			'status'      => 0,
			'sort_order'  => 1
		]);
		$this->model_setting_event->addEvent([
			'code'        => 'oasiscatalog_c_c_product_thumb',
			'description' => 'oasiscatalog, cdn hook image server',
			'trigger'     => 'catalog/controller/product/thumb/before',
			'action'      => 'extension/oasiscatalog/event/event.product_thumb',
			'status'      => 0,
			'sort_order'  => 1
		]);
		$this->model_setting_event->addEvent([
			'code'        => 'oasiscatalog_c_v_product_product',
			'description' => 'oasiscatalog, cdn hook image server',
			'trigger'     => 'catalog/view/product/product/before',
			'action'      => 'extension/oasiscatalog/event/event.product_product',
			'status'      => 0,
			'sort_order'  => 1
		]);
		$this->model_setting_event->addEvent([
			'code'        => 'oasiscatalog_c_v_checkout_cart_list',
			'description' => 'oasiscatalog, cdn hook image server',
			'trigger'     => 'catalog/view/checkout/cart_list/before',
			'action'      => 'extension/oasiscatalog/event/event.checkout_cart_list',
			'status'      => 0,
			'sort_order'  => 1
		]);
		$this->model_setting_event->addEvent([
			'code'        => 'oasiscatalog_c_v_common_cart',
			'description' => 'oasiscatalog, cdn hook image server',
			'trigger'     => 'catalog/view/common/cart/before',
			'action'      => 'extension/oasiscatalog/event/event.common_cart',
			'status'      => 0,
			'sort_order'  => 1
		]);
		$this->model_setting_event->addEvent([
			'code'        => 'oasiscatalog_c_v_product_compare',
			'description' => 'oasiscatalog, cdn hook image server',
			'trigger'     => 'catalog/view/product/compare/before',
			'action'      => 'extension/oasiscatalog/event/event.product_compare',
			'status'      => 0,
			'sort_order'  => 1
		]);
		$this->model_setting_event->addEvent([
			'code'        => 'oasiscatalog_c_v_account_wishlist_list',
			'description' => 'oasiscatalog, cdn hook image server',
			'trigger'     => 'catalog/view/account/wishlist_list/before',
			'action'      => 'extension/oasiscatalog/event/event.account_wishlist_list',
			'status'      => 0,
			'sort_order'  => 1
		]);
	}

	/**
	 * @throws Exception
	 */
	public function uninstall(): void
	{
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('oasiscatalog_a_v_catalog_product_form');
		$this->model_setting_event->deleteEventByCode('oasiscatalog_a_v_catalog_product_list');
		$this->model_setting_event->deleteEventByCode('oasiscatalog_c_c_product_thumb');
		$this->model_setting_event->deleteEventByCode('oasiscatalog_c_v_account_wishlist_list');
		$this->model_setting_event->deleteEventByCode('oasiscatalog_c_v_checkout_cart_list');
		$this->model_setting_event->deleteEventByCode('oasiscatalog_c_v_common_cart');
		$this->model_setting_event->deleteEventByCode('oasiscatalog_c_v_product_compare');
		$this->model_setting_event->deleteEventByCode('oasiscatalog_c_v_product_product');


		$cf = OasisConfig::instance($this->registry);
		$cf->deactivate();
	}

	/**
	 * @throws Exception
	 */
	public function get_data_progress_bar(): void
	{
		$this->load->language(self::ROUTE);

		$cf = OasisConfig::instance($this->registry, [
			'init' => true,
		]);
		$optBar = $cf->getOptBar();

		$step_text = '';
		if($optBar['steps']){
			if ($optBar['is_process']) {
				$step_text = sprintf($this->language->get('text_progress_step'), ($optBar['step'] + 1), $optBar['steps']);
			} else {
				$step_text = sprintf($this->language->get('text_progress_step_next'), ($optBar['step'] + 1), $optBar['steps']);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode([
			'is_process' => $optBar['is_process'],
			'progress_icon'   => $optBar['is_process'] ? '<i class="fa fa-cog fa-spin fa-fw" style="color: #0c7a0a;"></i>' :
														'<i class="fa fa-pause" aria-hidden="true" style="color: #e97906;"></i>',
			'p_total' => $optBar['p_total'],
			'p_step' => $optBar['p_step'],
			'step_text' => $step_text
		]));
	}

	public function get_all_categories(): void {
		$this->load->model('catalog/category');
		$this->load->language(self::ROUTE);

		$categories = $this->model_catalog_category->getCategories();

		$arr = [];
		foreach ($categories as $item) {
			if (empty($arr[$item['parent_id']])) {
				$arr[$item['parent_id']] = [];
			}
			$arr[$item['parent_id']][] = [
				'id' => $item['category_id'],
				'name' => $item['name'],
			];
		}

		$tree_content = '<div class="oa-tree">
				<div class="oa-tree-ctrl">
					<button type="button" class="btn btn-sm btn-light oa-tree-ctrl-m">'.$this->language->get('text_collapse_all').'</button>
					<button type="button" class="btn btn-sm btn-light oa-tree-ctrl-p">'.$this->language->get('text_expand_all').'</button>
				</div>' . Main::buildTreeRadioCats($arr) . '</div>';

		$this->response->setOutput($tree_content);
	}
}
