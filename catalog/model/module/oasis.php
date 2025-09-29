<?php

namespace Opencart\Catalog\Model\Extension\Oasiscatalog\Module;

use Opencart\System\Engine\Model;

class Oasis extends Model
{
	public function getImgsCDN(int $product_id): array
	{
		$query = $this->db->query("
			SELECT img.* FROM `" . DB_PREFIX . "oasis_cdn_images` as img
			LEFT JOIN `" . DB_PREFIX . "oasis_product` as p ON p.id = img.oasis_product_id
			WHERE p.product_id = ".$product_id
		);

		return $query->rows ?? [];
	}

	public function getOasisProduct(int $product_id, array $option = [])
	{
		if (empty($option)) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "oasis_product WHERE product_id = '" . $this->db->escape($product_id) . "'");
		}
		else {
			$option_value_id = implode(',', array_values($option));
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "oasis_product WHERE product_id = '" . $this->db->escape($product_id) . "' and option_value_id = '" . $this->db->escape($option_value_id) . "'");
		}
		return $query->row;
	}

	public function getProductOptionForName(string $name): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . "option` `o` LEFT JOIN `" . DB_PREFIX . "option_description` `od` ON (`o`.`option_id` = `od`.`option_id`) WHERE `od`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' AND LCASE(`od`.`name`) LIKE '" . $this->db->escape(oc_strtolower($name) . '%') . "' ORDER BY `od`.`name`";
		$query = $this->db->query($sql);
		return $query->row;
	}

	public function updateCartBranding(int $cart_id, array $branding, string $label): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "oasis_branding_cart` WHERE `cart_id` = '" . $cart_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "oasis_branding_cart` SET `cart_id` = '" . $cart_id . "', `branding` = '" . $this->db->escape(json_encode($branding)) . "', `label` = '" . $this->db->escape($label) . "'");
	}

	public function getCartBranding(int $cart_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "oasis_branding_cart` WHERE `cart_id` = '" . $cart_id . "'");
		$result = $query->row;
		if (empty($result)) {
			return null;
		}
		else {
			$result['branding'] = json_decode($result['branding'], true);
			return $result;
		}
	}

	public function upCartBrandingPrice(int $cart_id, float $price) {
		$this->db->query("UPDATE " . DB_PREFIX . "oasis_branding_cart SET price = '" . $this->db->escape(json_encode($price)) . "', price_up = '" . date('Y-m-d') . "' WHERE `cart_id` = '" . $cart_id . "'");
	}

	public function clearCartBrandingPrice(int $cart_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "oasis_branding_cart SET price = null, price_up = null WHERE `cart_id` = '" . $cart_id . "'");
	}

	public function updateOrderBranding(int $order_product_id, array $branding, string $label) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "oasis_branding_order` WHERE `order_product_id` = '" . $order_product_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "oasis_branding_order` SET `order_product_id` = '" . $order_product_id . "', `branding` = '" . $this->db->escape(json_encode($branding)) . "', `label` = '" . $this->db->escape($label) . "'");
	}
}