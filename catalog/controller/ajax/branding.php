<?php
namespace Opencart\Catalog\Controller\Extension\Oasiscatalog\Ajax;

require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/cli.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/api.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/main.php');
require_once(realpath(dirname(__FILE__) . '/../../..') . '/helper/config.php');

use Opencart\Admin\Controller\Extension\Oasis\Api;
use Opencart\Admin\Controller\Extension\Oasis\Config as OasisConfig;


class Branding extends \Opencart\System\Engine\Controller
{
	private const ROUTE = 'extension/oasiscatalog/module/oasis';

	public function get_info(): void {
		$product_id = $this->request->post['product_id'] ?? null;
		if (empty($product_id)) {
			return;
		}

		if (isset($this->request->post['option'])) {
			$option = array_filter((array)$this->request->post['option']);
		} else {
			$option = [];
		}

		$this->load->model(self::ROUTE);
		$product_oasis = $this->model_extension_oasiscatalog_module_oasis->getOasisProduct($product_id, $option);

		if (empty($product_oasis)) {
			return;
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(
			json_encode([
				'productId' => $product_oasis['product_id_oasis']
			])
		);
	}
}