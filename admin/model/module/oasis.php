<?php

namespace Opencart\Admin\Model\Extension\Oasiscatalog\Module;

use Opencart\System\Engine\Model;

class Oasis extends Model
{

	public function getImage(array $where)
	{
		$sql = "SELECT * FROM `" . DB_PREFIX . "oasis_images` WHERE `name` = '" . $this->db->escape($where['name']) . "'";

		if (!empty($where['path'])) {
			$sql .= " AND `path` = '" . $this->db->escape($where['path']) . "'";
		}

		$query = $this->db->query($sql);

		return $query->row;
	}

	public function addImage($data)
	{
		if (isset($data['name']) && isset($data['path']) && isset($data['date_added'])) {
			$this->db->query("
				INSERT INTO `" . DB_PREFIX . "oasis_images` (name, path, date_added) 
				SELECT * FROM (SELECT '" . $this->db->escape($data['name']) . "', '" . $this->db->escape($data['path']) . "', '" . (int)$data['date_added'] . "') AS tmp
				WHERE NOT EXISTS (
					SELECT name FROM `" . DB_PREFIX . "oasis_images` WHERE name = '" . $this->db->escape($data['name']) . "'
				) LIMIT 1
			");
		}
	}

	public function deleteImage($name): void
	{
		$this->db->query("DELETE FROM `" . DB_PREFIX . "oasis_images` WHERE `name` = '" . $this->db->escape($name) . "'");
	}

	public function addImgCDNFromOID(string $product_id_oasis, array $data): void
	{
		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "oasis_cdn_images` (oasis_product_id, main, url_superbig, url_big, url_small, url_thumbnail)
			SELECT p.id, ".$data['main'].", '".$this->db->escape($data['url_superbig'])."', '".$this->db->escape($data['url_big'])."', '".$this->db->escape($data['url_small'])."', '".$this->db->escape($data['url_thumbnail'])."'
			FROM `" . DB_PREFIX . "oasis_product` as p 
			where p.product_id_oasis = '".$this->db->escape($product_id_oasis)."'"
		);
	}

	public function delImgsCDNFromOID(string $oasisProductId): void
	{
		$this->db->query("
			DELETE img FROM `" . DB_PREFIX . "oasis_cdn_images` as img
			LEFT JOIN `" . DB_PREFIX . "oasis_product` as p ON p.id = img.oasis_product_id
			WHERE p.product_id_oasis = '".$this->db->escape($oasisProductId)."'"
		);
	}

	public function getImgsCDNFromOID(string $product_id_oasis): array
	{
		$query = $this->db->query("
			SELECT img.* FROM `" . DB_PREFIX . "oasis_cdn_images` as img
			LEFT JOIN `" . DB_PREFIX . "oasis_product` as p ON p.id = img.oasis_product_id
			WHERE p.product_id_oasis = '".$this->db->escape($product_id_oasis)."'
			ORDER BY img.id DESC"
		);

		return $query->rows ?? [];
	}

	public function getImgsCDN(int $product_id): array
	{
		$query = $this->db->query("
			SELECT img.* FROM `" . DB_PREFIX . "oasis_cdn_images` as img
			LEFT JOIN `" . DB_PREFIX . "oasis_product` as p ON p.id = img.oasis_product_id
			WHERE p.product_id = ".$product_id
		);

		return $query->rows ?? [];
	}

	public function setOption($store_id, $code, $key, $value)
	{
		if (!empty($code) && !empty($key)) {
			if (is_array($value)) {
				$serialized = 1;
				$value = json_encode($value);
			} else {
				$serialized = 0;
			}

			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting 
			WHERE `store_id` = '" . (int)$store_id . "' 
				AND `code` = '" . $this->db->escape($code) . "'
				AND `key` = '" . $this->db->escape($key) . "'");

			if ($query->row) {
				$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($value) . "', `serialized` = '" . $serialized . "' WHERE `setting_id` = '" . $query->row['setting_id'] . "'");
			} else {
				$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET `store_id` = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "', `serialized` = '" . $serialized . "'");
			}
		}
	}

	public function deleteOption($store_id, $code, $key)
	{
		if (!empty($code) && !empty($key)) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "setting 
			WHERE `store_id` = '" . (int)$store_id . "' 
				AND `code` = '" . $this->db->escape($code) . "'
				AND `key` = '" . $this->db->escape($key) . "'");
		}
	}

	public function addOrder($data)
	{
		if (isset($data['order_id']) && isset($data['queue_id'])) {
			$this->db->query("
				INSERT INTO `" . DB_PREFIX . "oasis_order` (order_id, queue_id) 
				SELECT * FROM (SELECT '" . (int)$data['order_id'] . "', '" . (int)$data['queue_id'] . "') AS tmp
				WHERE NOT EXISTS (
					SELECT order_id FROM `" . DB_PREFIX . "oasis_order` WHERE order_id = '" . (int)$data['order_id'] . "'
				) LIMIT 1
			");
		}
	}

	public function getOrder($order_id)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "oasis_order WHERE order_id = '" . (int)$order_id . "'");

		return $query->row;
	}

	public function addOasisProduct(array $data)
	{
		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "oasis_product` (product_id_oasis, updated_at, images_updated_at, option_value_id, product_id) 
			SELECT * FROM (SELECT '" . $this->db->escape($data['product_id_oasis']) . "' product_id_oasis,
					'" . $this->db->escape($data['updated_at']) . "' updated_at,
					'" . $this->db->escape($data['images_updated_at']) . "' images_updated_at,
					'" . (int)$data['option_value_id'] . "' option_value_id,
					'" . (int)$data['product_id'] . "' product_id) AS tmp
			WHERE NOT EXISTS (
				SELECT product_id_oasis FROM `" . DB_PREFIX . "oasis_product` WHERE product_id_oasis = '" . $this->db->escape($data['product_id_oasis']) . "'
			) LIMIT 1
		");
	}

	public function editOasisProduct(string $oasisProductId, array $data)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "oasis_product
						SET  option_value_id = '" . (int)$data['option_value_id'] . "',
						product_id = '" . (int)$data['product_id'] . "',
						updated_at = '" . $this->db->escape($data['updated_at']) . "',
						images_updated_at = '" . $this->db->escape($data['images_updated_at']) . "'
					WHERE product_id_oasis = '" . $this->db->escape($oasisProductId) . "'");
	}

	public function deleteOasisProduct(string $oasisProductId)
	{
		$this->delImgsCDNFromOID($oasisProductId);
		$this->db->query("DELETE FROM `" . DB_PREFIX . "oasis_product` WHERE product_id_oasis = '" . $this->db->escape($oasisProductId) . "'");
	}

	public function getOasisProduct(string $oasisProductId): array
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "oasis_product WHERE product_id_oasis = '" . $this->db->escape($oasisProductId) . "'");
		return $query->row;
	}

	public function getOasisProducts()
	{
		$query = $this->db->query("SELECT product_id_oasis, option_value_id, product_id FROM " . DB_PREFIX . "oasis_product");

		return $query->rows;
	}

	public function getOasisProductsForOCID(int $productId)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "oasis_product WHERE product_id = '" . $this->db->escape($productId) . "'");
		return $query->rows;
	}

	public function getGroupOasisProducts(string $oasisProductId)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "oasis_product WHERE product_id =
									(SELECT product_id FROM " . DB_PREFIX . "oasis_product WHERE product_id_oasis = '" . $this->db->escape($oasisProductId) . "')
								  ORDER BY id");
		return $query->rows;
	}

	public function upProductQuantity($productId, $quantity)
	{
		$status = $quantity > 0 ? 1 : 0;
		$this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = '" . $this->db->escape($quantity) . "', status = '" . $status . "' WHERE product_id = '" . $this->db->escape($productId) . "'");
	}

	public function getProductOptionValues($product_id)
	{
		$query = $this->db->query("SELECT product_option_value_id, quantity FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "'");

		return $query->rows;
	}

	public function getProductOptionValueId(int $product_id, int $option_value_id)
	{
		$query = $this->db->query("SELECT product_option_value_id FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . $product_id . "' AND option_value_id = '" . $option_value_id . "'");

		return $query->row['product_option_value_id'] ?? null;
	}

	public function upProductOptionValue($product_option_value_id, $quantity)
	{
		$this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = '" . (int)$quantity . "' WHERE product_option_value_id = '" . (int)$product_option_value_id . "'");
	}

	public function getCategory(int $category_id): array
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category WHERE category_id = '" . $category_id . "'");

		return $query->row;
	}

	public function getCategoryPath($category_id)
	{
		$query = $this->db->query("SELECT category_id, path_id, level FROM " . DB_PREFIX . "category_path WHERE category_id = '" . (int)$category_id . "'");

		return $query->rows;
	}

	public function getSeoUrls(array $where): array
	{
		$sql = "SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE `keyword` = '" . $this->db->escape($where['keyword']) . "'";

		if (!empty($where['key'])) {
			$sql .= " AND `key` = '" . $this->db->escape($where['key']) . "'";
		}

		if (!empty($where['value'])) {
			$sql .= " AND `value` = '" . $this->db->escape($where['value']) . "'";
		}

		$query = $this->db->query($sql);

		return $query->row;
	}

	public function getIdOcCategory(int $id): array
	{
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "oasis_category` WHERE oasis_id = '" . $id . "'");

		return $query->row;
	}

	public function addOasisCategory($data)
	{
		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "oasis_category` (oasis_id, category_id) 
			SELECT * FROM (SELECT '" . (int)$data['oasis_id'] . "' oasis_id, '" . $this->db->escape($data['category_id']) . "' category_id) AS tmp
			WHERE NOT EXISTS (
				SELECT oasis_id FROM `" . DB_PREFIX . "oasis_category` WHERE oasis_id = '" . (int)$data['oasis_id'] . "'
			) LIMIT 1
		");
	}

	public function deleteOasisCategory(int $category_id): void
	{
		$this->db->query("DELETE FROM `" . DB_PREFIX . "oasis_category` WHERE `category_id` = '" . $category_id . "'");
	}

	public function setProductImage(int $product_id, string $image): void
	{
		$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `image` = '" . $this->db->escape((string)$image) . "' WHERE `product_id` = '" . (int)$product_id . "'");
	}

	public function getOrderBranding(int $order_product_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "oasis_branding_order` WHERE `order_product_id` = '" . $order_product_id . "'");
		$result = $query->row;
		if (empty($result)) {
			return null;
		}
		else {
			$result['branding'] = json_decode($result['branding'], true);
			return $result;
		}
	}


	public function install(): void
	{
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "oasis_order` (
		  `order_id` INT(11) NOT NULL,
		  `queue_id` INT(11) NOT NULL,
		  PRIMARY KEY(`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "oasis_category` (
		  `oasis_id` CHAR(12) NOT NULL,
		  `category_id` INT NOT NULL,
		  PRIMARY KEY (`oasis_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "oasis_images` (
		  `name` char(50) NOT NULL,
		  `path` char(255) NOT NULL,
		  `date_added` int unsigned NOT NULL,
		  PRIMARY KEY (`name`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "oasis_product` (
		  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  `product_id_oasis` CHAR(12) NOT NULL,
		  `updated_at` CHAR(30) NOT NULL,
		  `images_updated_at` CHAR(30) NOT NULL,
		  `option_value_id` INT(11) NOT NULL DEFAULT '0',
		  `product_id` INT(11) NOT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "oasis_cdn_images` (
		  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  `oasis_product_id` INT(11) NOT NULL,
		  `main` TINYINT(1) NOT NULL DEFAULT 0,
		  `url_superbig` char(255) NOT NULL,
		  `url_big` char(255) NOT NULL,
		  `url_small` char(255) NOT NULL,
		  `url_thumbnail` char(255) NOT NULL,
		  FOREIGN KEY (`oasis_product_id`)
		    REFERENCES `" . DB_PREFIX . "oasis_product`(`id`)
		    ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "oasis_branding_cart` (
		  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  `cart_id` INT(11) NOT NULL,
		  `branding` TEXT NOT NULL,
		  `label` CHAR(255) NOT NULL,
		  `price` DECIMAL(15,2),
		  `price_up` datetime,
		  FOREIGN KEY (`cart_id`)
		    REFERENCES `" . DB_PREFIX . "cart`(`cart_id`)
		    ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "oasis_branding_order` (
		  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  `order_product_id` INT(11) NOT NULL,
		  `label` CHAR(255) NOT NULL,
		  `branding` TEXT NOT NULL,
		  FOREIGN KEY (`order_product_id`)
		    REFERENCES `" . DB_PREFIX . "order_product`(`order_product_id`)
		    ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
	}

	public function uninstall(): void {
		foreach ([
			'oasis_order',
			'oasis_category',
			'oasis_images',
			'oasis_product',
			'oasis_cdn_images',
			'oasis_branding_cart',
			'oasis_branding_order',
		] as $table) {
			$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . $table . "`");
		}
	}
}