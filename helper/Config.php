<?php
namespace Opencart\Admin\Controller\Extension\Oasis;

use Opencart\Admin\Controller\Extension\Oasis\Main;
use Opencart\Admin\Controller\Extension\Oasis\Api;

use \Opencart\System\Engine\Registry;


class Config {
	private Registry $registry;


	public bool $is_debug = false;
	public bool $is_debug_log = false;
	public string $upload_path;

	public bool $status = false;

	public string $api_key;
	public string $api_user_id;

	public string $currency;

	public array $categories;
	public array $categories_rel;
	private array $categories_easy;
	public ?int $category_rel;
	public string $category_rel_label;

	public array $progress;

	public array $currencies;

	public bool $is_not_up_cat;
	public bool $is_import_anytime;

	public ?int $limit;

	public ?\DateTime $import_date;

	public ?float $price_factor;
	public ?float $price_increase;
	public bool $is_price_dealer;

	public bool $is_no_vat;
	public ?int $tax_class_id;
	public bool $is_not_on_order;
	public ?float $price_from;
	public ?float $price_to;
	public ?int $rating;
	public bool $is_wh_moscow;
	public bool $is_wh_europe;
	public bool $is_wh_remote;

	public bool $is_up_photo;
	public bool $is_delete_exclude;

	private bool $is_init_rel = false;

	private static $instance;


	public static function instance(?Registry $registry = null, $opt = []) {
		if (!isset(self::$instance)) {
			if(empty($registry)){
				throw new Exception('Opencart Registry empty');
			}
			self::$instance = new self($registry, $opt);
		}

		return self::$instance;
	}

	public function __construct(Registry $registry, $opt = []) {
		$this->registry = $registry;

		$this->upload_path = DIR_STORAGE . 'wp-oasis-importer';

		$this->is_debug = !empty($opt['debug']);
		$this->is_debug_log = !empty($opt['debug_log']);

		Main::$cf = $this;
		Api::$cf = $this;

		if(!empty($opt['init'])){
			$this->init();
		}
		if(!empty($opt['init_rel'])){
			$this->initRelation();
		}
		if(!empty($opt['load_currencies'])){
			$this->loadCurrencies();
		}
	}

	public function init() {
		if(!isset($this->registry->model_setting_setting)){
			$this->registry->load->model('setting/setting');
		}

		$attr = $this->registry->model_setting_setting->getSetting('module_oasis');

		$this->progress = $attr['module_oasis_progress'] ?? [
			'item' => 0,			// count updated products
			'total' => 0,			// count all products
			'step' => 0,			// step (for limit)
			'step_item' => 0,		// count updated products for step
			'step_total' => 0,		// count step total products
			'date' => '',			// date end import
			'date_step' => ''		// date end import for step
		];

		$this->api_key =		$attr['module_oasis_api_key'] ?? '';
		$this->api_user_id =	$attr['module_oasis_user_id'] ?? '';
		$this->status =			!empty($attr['module_oasis_status']);

		$opt = $attr['module_oasis_opt'] ?? [];

		$this->currency =		$opt['currency'] ?? 'rub';
		$this->limit =			!empty($opt['limit']) ? intval($opt['limit']) : null;

		$this->categories =		$opt['categories'] ?? [];

		$cat_rel = $opt['categories_rel'] ?? [];
		$this->categories_rel = [];
		foreach($cat_rel as $rel){
			$rel = 	explode('_', $rel);
			$cat_id = (int)$rel[0];
			$rel_id = (int)$rel[1];

			$this->categories_rel[$cat_id] = [
				'id' =>  $rel_id,
				'rel_label' => null
			];
		}
		
		$this->price_factor =			!empty($opt['price_factor']) ? floatval(str_replace(',', '.', $opt['price_factor'])) : null;
		$this->price_increase =			!empty($opt['price_increase']) ? floatval(str_replace(',', '.', $opt['price_increase'])) : null;
		$this->is_price_dealer =		!empty($opt['is_price_dealer']);

		$this->is_import_anytime =		!empty($opt['is_import_anytime']);
		$dt = null;
		if(!empty($this->progress['date'])){
			$dt = \DateTime::createFromFormat('d.m.Y H:i:s', $this->progress['date']);
		}
		$this->import_date = $dt;

		$this->category_rel = 			!empty($opt['category_rel']) ? intval($opt['category_rel']) : null;
		$this->category_rel_label = 	'';
		$this->is_not_up_cat =			!empty($opt['is_not_up_cat']);

		$this->is_no_vat =				!empty($opt['is_no_vat']);
		$this->tax_class_id =			!empty($opt['tax_class_id']) ? intval($opt['tax_class_id']) : null;
		$this->is_not_on_order =		!empty($opt['is_not_on_order']);
		$this->price_from =				!empty($opt['price_from']) ? floatval(str_replace(',', '.', $opt['price_from'])) : null;
		$this->price_to =				!empty($opt['price_to']) ? floatval(str_replace(',', '.', $opt['price_to'])) : null;
		$this->rating =					!empty($opt['rating']) ? intval($opt['rating']) : null;
		$this->is_wh_moscow =			!empty($opt['is_wh_moscow']);
		$this->is_wh_europe =			!empty($opt['is_wh_europe']);
		$this->is_wh_remote =			!empty($opt['is_wh_remote']);
		$this->is_up_photo =			!empty($opt['is_up_photo']);
		$this->is_delete_exclude =		!empty($opt['is_delete_exclude']);
	}

	public function initRelation() {
		if($this->is_init_rel) {
			return;
		}

		if(!isset($this->registry->model_catalog_category)) {
			$this->registry->load->model('catalog/category');
		}

		foreach($this->categories_rel as $cat_id => $rel) {
			$this->categories_rel[$cat_id]['rel_label'] = $this->getRelLabel($rel['id']);
		}
		if(isset($this->category_rel)) {
			$this->category_rel_label = $this->getRelLabel($this->category_rel);
		}

		$this->is_init_rel = true;
	}

	private function getRelLabel(int $cat_id) {
		$list = [];
		while($cat_id != 0){
			$category = $this->registry->model_catalog_category->getCategory($cat_id);
			$list []= $category['name'];
			$cat_id = $category['parent_id'];
		}
		return implode(' / ', array_reverse($list));
	}

	public function progressStart(int $total, int $step_total) {
		$this->progress['total'] = $total;
		$this->progress['step_total'] = $step_total;
		$this->progress['step_item'] = 0;
		$this->updateSettingProgress();
	}

	public function progressUp() {
		$this->progress['step_item']++;
		$this->updateSettingProgress();
	}

	public function progressEnd() {
		$dt = (new \DateTime())->format('d.m.Y H:i:s');
		$this->progress['date_step'] = $dt;

		if($this->limit > 0){
			$this->progress['item'] += $this->progress['step_item'];

			if(($this->limit * ($this->progress['step'] + 1)) > $this->progress['total']){
				$this->progress['step'] = 0;
				$this->progress['item'] = 0;
				$this->progress['date'] = $dt;
			}
			else{
				$this->progress['step']++;
			}
		}
		else{
			$this->progress['date'] = $dt;
			$this->progress['item'] = 0;
		}

		$this->progress['step_item'] = 0;
		$this->progress['step_total'] = 0;

		$this->updateSettingProgress();
	}

	public function progressClear() {
		$this->progress = [
			'item' => 0,			// count updated products
			'total' => 0,			// count all products
			'step' => 0,			// step (for limit)
			'step_item' => 0,		// count updated products for step
			'step_total' => 0,		// count step total products
			'date' => '',			// date end import
			'date_step' => ''		// date end import for step
		];
		$this->updateSettingProgress();
	}

	private function updateSettingProgress () {
		if(!isset($this->registry->model_setting_setting)){
			$this->registry->load->model('setting/setting');
		}
		$this->registry->model_setting_setting->editValue('module_oasis', 'module_oasis_progress', $this->progress);
	}

	public function getOptBar() {
		$is_process = $this->checkLockProcess();

		$opt = $this->progress;
		$p_total = 0;
		$p_step = 0;

		if (!empty($opt['step_item']) && !empty($opt['step_total'])) {
			$p_step = round(($opt['step_item'] / $opt['step_total']) * 100, 2, PHP_ROUND_HALF_DOWN );
			$p_step = min($p_step, 100);
		}

		if (!(empty($opt['item']) && empty($opt['step_item'])) && !empty($opt['total'])) {
			$p_total = round((($opt['item'] + $opt['step_item']) / $opt['total']) * 100, 2, PHP_ROUND_HALF_DOWN );
			$p_total = min($p_total, 100);
		}

		return [
			'is_process' =>	$is_process,
			'p_total' =>	$p_total,
			'p_step' =>		$p_step,
			'step' =>		$opt['step'] ?? 0,
			'steps' =>		($this->limit > 0 && !empty($opt['total'])) ? (ceil($opt['total'] / $this->limit)) : 0,
			'date' =>		$opt['date_step'] ?? ''
		];
	}

	public function checkCronKey(string $cron_key): bool {
		return $cron_key === md5($this->api_key);
	}

	public function getCronKey(): string {
		return md5($this->api_key);
	}

	public function checkApi(): bool {
		return !empty(Api::getCurrenciesOasis());
	}

	public function lock($fn, $fn_error) {
		$lock = fopen($this->upload_path . '/start.lock', 'w');
		if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
			$fn();
		}
		else{
			$fn_error();
		}
	}

	public function checkLockProcess(): bool {
		$lock = fopen($this->upload_path . '/start.lock', 'w');
		if (!($lock && flock( $lock, LOCK_EX | LOCK_NB ))) {
			return true;
		}
		return false;
	}

	public function checkPermissionImport(): bool {
		if(!$this->is_import_anytime && 
			$this->import_date &&
			$this->import_date->format("Y-m-d") == (new \DateTime())->format("Y-m-d")){
				return false;
		}
		return true;
	}

	public function log($str) {
		if ($this->is_debug || $this->is_debug_log) {
			$str = date('H:i:s').' '.$str;

			if ($this->is_debug_log) {
				file_put_contents($this->upload_path . '/oasis_'.date('Y-m-d').'.log', $str . "\n", FILE_APPEND);
			} else {
				echo $str . PHP_EOL;
			}
		}
	}

	public function deleteLogFile() {
		$filePath = $this->upload_path . '/oasis.log';
		if (file_exists($filePath)) {
			unlink($filePath);
		}
	}

	public function getRelCategoryId($oasis_cat_id) {
		if(isset($this->categories_rel[$oasis_cat_id])){
			return $this->categories_rel[$oasis_cat_id]['id'];
		}
		if(isset($this->category_rel)){
			return $this->category_rel;
		}
		return null;
	}

	public function getEasyCategories() {
		
	}

	public function loadCurrencies(): bool {
		$data = Api::getCurrenciesOasis();
		if(empty($data))
			return false;

		$currencies = [];
		foreach ($data as $currency) {
			$currencies[] = [
				'code' => $currency->code,
				'name' => $currency->full_name
			];
		}
		$this->currencies = $currencies;
		return true;
	}

	public function activate() {
		if (!is_dir($this->upload_path)) {
			if(!mkdir($this->upload_path, 0755, true)){
				die('Failed to create directories: ' . $this->upload_path);
			}
		}

		if(!isset($this->registry->model_setting_setting)){
			$this->registry->load->model('setting/setting');
		}

		$this->registry->model_setting_setting->editSetting('module_oasis', [
			'module_oasis_status' => 0
		]);
	}

	public function deactivate() {
		if(!isset($this->registry->model_setting_setting)){
			$this->registry->load->model('setting/setting');
		}
		$this->registry->model_setting_setting->deleteSetting('module_oasis');

		$this->rmdDir($this->upload_path);
	}

	private function rmdDir($dir) {
		foreach (glob( $dir . '/*') as $file) {
			if (is_dir($file)){
				self::rmdDir($file);
			} else {
				unlink($file);
			}
		}
		rmdir($dir);
	}
}